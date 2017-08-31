<?php

namespace AppBundle\Controller\Admin;

use Biz\Order\OrderRefundProcessor\OrderRefundProcessorFactory;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Common\Paginator;
use AppBundle\Common\ArrayToolkit;
use AppBundle\Common\StringToolkit;

class OrderRefundController extends BaseController
{
    public function refundsAction(Request $request, $targetType)
    {
        //$conditions = $this->prepareRefundSearchConditions($request->query->all());
        $conditions = $request->query->all();
        $paginator = new Paginator(
            $request,
            $this->getOrderRefundService()->countRefunds($conditions),
            20
        );

        $refunds = $this->getOrderRefundService()->searchRefunds(
            $conditions,
            array('created_time' => 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $orderIds = ArrayToolkit::column($refunds, 'order_id');

        $userIds = array_merge(ArrayToolkit::column($refunds, 'created_user_id'), ArrayToolkit::column($refunds, 'deal_user_id'));
        $users = $this->getUserService()->findUsersByIds($userIds);
        
        $orders = $this->getOrderService()->findOrdersByIds($orderIds);
        $orders = ArrayToolkit::index($orders, 'id');

        $orderItems = $this->getOrderService()->findOrderItemsByorderIds($orderIds);
        $orderItems = ArrayToolkit::index($orderItems, 'order_id');

        return $this->render("admin/order-refund/refund-{$targetType}.html.twig", array(
            'refunds' => $refunds,
            'orderItems' => $orderItems,
            'users' => $users,
            'orders' => $orders,
            'paginator' => $paginator,
            'targetType' => $targetType,
        ));
    }

    protected function prepareRefundSearchConditions($conditions)
    {
        $conditions = array_filter($conditions);

        if (!empty($conditions['orderSn'])) {
            $order = $this->getOrderService()->getOrderBySn($conditions['orderSn']);
            $conditions['orderId'] = $order ? $order['id'] : -1;
            unset($conditions['orderSn']);
        }

        if (!empty($conditions['nickname'])) {
            $user = $this->getUserService()->getUserByNickname($conditions['nickname']);
            $conditions['userId'] = $user ? $user['id'] : -1;
            unset($conditions['nickname']);
        }

        return $conditions;
    }

    public function auditRefundAction(Request $request, $id)
    {
        $order = $this->getOrderService()->getOrder($id);
        $trade = $this->getPayService()->getTradeByTradeSn($order['trade_sn']);

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();

            $pass = $data['result'] == 'pass' ? true : false;

            $this->getOrderService()->auditRefundOrder($order['id'], $pass, $data['amount'], $data['note']);
            $orderRefundProcessor = $this->getOrderRefundProcessor($order['targetType']);
            $orderRefundProcessor->auditRefundOrder($id, $pass, $data);

            if ($order['targetType'] == 'course') {
                $this->sendAuditRefundNotification($orderRefundProcessor, $order, $data);
            } else {
                if ($pass) {
                    $this->getNotificationService()->notify($order['userId'], 'order_refund', array('type' => 'audit_pass'));
                } else {
                    $this->getNotificationService()->notify($order['userId'], 'order_refund', array('type' => 'audit_reject', 'reason' => $data['note']));
                }
            }

            return $this->createJsonResponse(true);
        }

        return $this->render('admin/order-refund/refund-confirm-modal.html.twig', array(
            'order' => $order,
            'trade' => $trade,
        ));
    }

    protected function sendAuditRefundNotification($orderRefundProcessor, $order, $data)
    {
        $target = $orderRefundProcessor->getTarget($order['targetId']);
        if (empty($target)) {
            return false;
        }

        if ($data['result'] == 'pass') {
            $message = $this->setting('refund.successNotification', '');
        } else {
            $message = $this->setting('refund.failedNotification', '');
        }

        if (empty($message)) {
            return false;
        }

        $targetUrl = $this->generateUrl($order['targetType'].'_show', array('id' => $order['targetId']));
        $variables = array(
            'item' => "<a href='{$targetUrl}'>{$target['title']}</a>",
            'amount' => $data['amount'],
            'note' => $data['note'],
        );

        $message = StringToolkit::template($message, $variables);
        $this->getNotificationService()->notify($order['userId'], 'default', $message);
    }

    protected function getOrderService()
    {
        return $this->createService('Order:OrderService');
    }

    protected function getOrderRefundService()
    {
        return $this->createService('Order:OrderRefundService');
    }

    protected function getNotificationService()
    {
        return $this->createService('User:NotificationService');
    }

    protected function getPayService()
    {
        return $this->createService('Pay:PayService');
    }
}
