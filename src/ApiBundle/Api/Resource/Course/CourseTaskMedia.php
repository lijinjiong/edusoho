<?php

namespace ApiBundle\Api\Resource\Course;

use ApiBundle\Api\ApiRequest;
use ApiBundle\Api\Resource\AbstractResource;
use Biz\Activity\Service\ActivityService;
use Biz\Course\Service\CourseService;
use Biz\Task\Service\TaskService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CourseTaskMedia extends AbstractResource
{
    public function get(ApiRequest $request, $courseId, $taskId)
    {
        $ssl = $request->getHttpRequest()->isSecure() ? true : false;
        $course = $this->getCourseService()->tryTakeCourse($courseId);
        $task = $this->getTaskService()->tryTakeTask($taskId);
        $activity = $this->getActivityService()->getActivity($task['activityId'], true);
        $method = 'get'.$activity['mediaType'];
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(sprintf('Unknown property "%s" on TaskMedia "%s".', $activity['mediaType'], get_class($this)));
        }
        $media = $this->$method($course, $task, $activity, $ssl);

        return array(
            'mediaType' => $activity['mediaType'],
            'media' => $media,
        );
    }

    protected function getVideo($course, $task, $activity, $ssl = false)
    {
        $type = $this->getActivityService()->getActivityConfig($activity['mediaType']);
        $video = $type->get($activity['mediaId']);
        $watchStatus = $type->getWatchStatus($activity);
        if ('error' === $watchStatus['status']) {
            throw new AccessDeniedHttpException('您的视频观看时长已达限制，无法继续观看！');
        }

        $video = $type->prepareMediaUri($video);

        if ('self' != $video['mediaSource']) {
            return $video;
        }
    }

    protected function getAudio($course, $task, $activity, $ssl = false)
    {
    }

    protected function getDoc($course, $task, $activity, $ssl = false)
    {
    }

    protected function getPpt($course, $task, $activity, $ssl = false)
    {
    }

    protected function getLive($course, $task, $activity, $ssl = false)
    {
    }

    protected function getText($course, $task, $activity, $ssl = false)
    {
        return array(
            'title' => $activity['title'],
            'content' => $activity['content'],
        );
    }

    protected function getVideoAndAudioPlayer()
    {
    }

    /**
     * @return CourseService
     */
    protected function getCourseService()
    {
        return $this->getBiz()->service('Course:CourseService');
    }

    /**
     * @return TaskService
     */
    protected function getTaskService()
    {
        return $this->getBiz()->service('Task:TaskService');
    }

    /**
     * @return ActivityService
     */
    protected function getActivityService()
    {
        return $this->getBiz()->service('Activity:ActivityService');
    }
}
