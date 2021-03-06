<?php

/*
 * This file is part of the Qsnh/meedu.
 *
 * (c) XiaoTeng <616896861@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use App\Constant\ApiV2Constant;
use App\Businesses\BusinessState;
use App\Http\Requests\ApiV2\CommentRequest;
use App\Services\Base\Services\ConfigService;
use App\Services\Member\Services\UserService;
use App\Services\Order\Services\OrderService;
use App\Services\Course\Services\VideoService;
use App\Services\Course\Services\CourseService;
use App\Services\Course\Services\CourseCommentService;
use App\Services\Base\Interfaces\ConfigServiceInterface;
use App\Services\Member\Interfaces\UserServiceInterface;
use App\Services\Order\Interfaces\OrderServiceInterface;
use App\Services\Course\Interfaces\VideoServiceInterface;
use App\Services\Course\Interfaces\CourseServiceInterface;
use App\Services\Course\Interfaces\CourseCommentServiceInterface;

/**
 * Class CourseController
 * @package App\Http\Controllers\Api\V2
 */
class CourseController extends BaseController
{

    /**
     * @var CourseService
     */
    protected $courseService;
    /**
     * @var ConfigService
     */
    protected $configService;
    /**
     * @var CourseCommentService
     */
    protected $courseCommentService;
    /**
     * @var UserService
     */
    protected $userService;
    /**
     * @var VideoService
     */
    protected $videoService;
    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var BusinessState
     */
    protected $businessState;

    public function __construct(
        CourseServiceInterface $courseService,
        ConfigServiceInterface $configService,
        CourseCommentServiceInterface $courseCommentService,
        UserServiceInterface $userService,
        VideoServiceInterface $videoService,
        OrderServiceInterface $orderService,
        BusinessState $businessState
    ) {
        $this->courseService = $courseService;
        $this->configService = $configService;
        $this->courseCommentService = $courseCommentService;
        $this->userService = $userService;
        $this->videoService = $videoService;
        $this->orderService = $orderService;
        $this->businessState = $businessState;
    }

    /**
     * @OA\Get(
     *     path="/courses",
     *     summary="????????????",
     *     tags={"??????"},
     *     @OA\Parameter(in="query",name="page",description="??????",required=false,@OA\Schema(type="integer")),
     *     @OA\Parameter(in="query",name="page_size",description="????????????",required=false,@OA\Schema(type="integer")),
     *     @OA\Parameter(in="query",name="category_id",description="??????id",required=false,@OA\Schema(type="integer")),
     *     @OA\Parameter(in="query",name="scene",description="??????[???:????????????,recom:??????,sub:????????????,free:????????????]",required=false,@OA\Schema(type="string")),
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description="",
     *                 @OA\Property(property="total",type="integer",description="??????"),
     *                 @OA\Property(property="data",type="array",description="????????????",@OA\Items(ref="#/components/schemas/Course")),
     *             ),
     *         )
     *     )
     * )
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paginate(Request $request)
    {
        $categoryId = intval($request->input('category_id'));
        $scene = $request->input('scene', '');
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', $this->configService->getCourseListPageSize());
        [
            'total' => $total,
            'list' => $list
        ] = $this->courseService->simplePage($page, $pageSize, $categoryId, $scene);
        $list = arr2_clear($list, ApiV2Constant::MODEL_COURSE_FIELD);
        $courses = $this->paginator($list, $total, $page, $pageSize);

        return $this->data($courses->toArray());
    }

    /**
     * @OA\Get(
     *     path="/course/{id}",
     *     @OA\Parameter(in="path",name="id",description="??????id",required=true,@OA\Schema(type="integer")),
     *     summary="????????????",
     *     tags={"??????"},
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description="",
     *                 @OA\Property(property="course",type="object",description="????????????",ref="#/components/schemas/Course"),
     *                 @OA\Property(property="chapters",type="array",description="????????????",@OA\Items(ref="#/components/schemas/CourseChapter")),
     *                 @OA\Property(property="videos",type="array",description="??????",@OA\Items(ref="#/components/schemas/Video")),
     *                 @OA\Property(property="isBuy",type="bool",description="????????????"),
     *                 @OA\Property(property="isCollect",type="bool",description="????????????"),
     *             ),
     *         )
     *     )
     * )
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        $course = $this->courseService->find($id);
        $course = arr1_clear($course, ApiV2Constant::MODEL_COURSE_FIELD);

        // ????????????
        $chapters = $this->courseService->chapters($course['id']);
        $chapters = arr2_clear($chapters, ApiV2Constant::MODEL_COURSE_CHAPTER_FIELD);

        // ????????????
        $videos = $this->videoService->courseVideos($course['id']);
        $videos = arr2_clear($videos, ApiV2Constant::MODEL_VIDEO_FIELD, true);

        // ????????????
        $isBuy = false;
        // ????????????
        $isCollect = false;
        // ????????????????????????
        $videoWatchedProgress = [];

        // ????????????
        $attach = $this->courseService->getCourseAttach($course['id']);
        $attach = arr2_clear($attach, ApiV2Constant::MODEL_COURSE_ATTACH_FIELD);

        if ($this->check()) {
            $isBuy = $this->businessState->isBuyCourse($this->id(), $course['id']);
            $isCollect = $this->userService->likeCourseStatus($this->id(), $course['id']);

            $userVideoWatchRecords = $this->userService->getUserVideoWatchRecords($this->id(), $course['id']);
            $videoWatchedProgress = array_column($userVideoWatchRecords, null, 'video_id');
        }

        return $this->data(compact('course', 'chapters', 'videos', 'isBuy', 'isCollect', 'videoWatchedProgress', 'attach'));
    }

    /**
     * @OA\Post(
     *     path="/course/{id}/comment",
     *     @OA\Parameter(in="path",name="id",description="??????id",required=true,@OA\Schema(type="integer")),
     *     summary="????????????",
     *     tags={"??????"},
     *     @OA\RequestBody(description="",@OA\JsonContent(
     *         @OA\Property(property="content",description="????????????",type="string"),
     *     )),
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description=""),
     *         )
     *     )
     * )
     * @param CommentRequest $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function createComment(CommentRequest $request, $id)
    {
        $course = $this->courseService->find($id);
        if ($this->businessState->courseCanComment($this->user(), $course) == false) {
            return $this->error(__('course cant comment'));
        }
        ['content' => $content] = $request->filldata();
        $this->courseCommentService->create($id, $content);
        return $this->success();
    }

    /**
     * @OA\Get(
     *     path="/course/{id}/comments",
     *     @OA\Parameter(in="query",name="page",description="??????",required=false,@OA\Schema(type="integer")),
     *     @OA\Parameter(in="query",name="page_size",description="????????????",required=false,@OA\Schema(type="integer")),
     *     @OA\Parameter(in="path",name="id",description="??????id",required=true,@OA\Schema(type="integer")),
     *     summary="??????????????????",
     *     tags={"??????"},
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description="",
     *                 @OA\Property(property="comments",type="array",description="??????",@OA\Items(ref="#/components/schemas/CourseComment")),
     *                 @OA\Property(property="users",type="array",description="????????????",@OA\Items(ref="#/components/schemas/User")),
     *             ),
     *         )
     *     )
     * )
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function comments($id)
    {
        $comments = $this->courseCommentService->courseComments($id);
        $comments = arr2_clear($comments, ApiV2Constant::MODEL_COURSE_COMMENT_FIELD);
        $commentUsers = $this->userService->getList(array_column($comments, 'user_id'), ['role']);
        $commentUsers = arr2_clear($commentUsers, ApiV2Constant::MODEL_MEMBER_FIELD);
        $commentUsers = array_column($commentUsers, null, 'id');

        return $this->data([
            'comments' => $comments,
            'users' => $commentUsers,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/course/{id}/like",
     *     @OA\Parameter(in="path",name="id",description="??????id",required=true,@OA\Schema(type="integer")),
     *     summary="????????????",
     *     tags={"??????"},
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description=""),
     *         )
     *     )
     * )
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function like($id)
    {
        $course = $this->courseService->find($id);
        $status = $this->userService->likeACourse($this->id(), $course['id']);
        return $this->data($status);
    }

    /**
     * @OA\Get(
     *     path="/course/attach/{id}/download",
     *     @OA\Parameter(in="path",name="id",description="????????????id",required=true,@OA\Schema(type="integer")),
     *     summary="??????????????????",
     *     tags={"??????"},
     *     @OA\Response(
     *         description="",response=200,
     *         @OA\JsonContent(
     *             @OA\Property(property="code",type="integer",description="?????????"),
     *             @OA\Property(property="message",type="string",description="??????"),
     *             @OA\Property(property="data",type="object",description=""),
     *         )
     *     )
     * )
     * @param $id
     */
    public function attachDownload($id)
    {
        $courseAttach = $this->courseService->getAttach($id);
        if (!$this->businessState->isBuyCourse($this->id(), $courseAttach['course_id'])) {
            return $this->error(__('please buy course'));
        }
        $this->courseService->courseAttachDownloadTimesInc($courseAttach['id']);
        return response()->download(storage_path('app/attach/' . $courseAttach['path']));
    }
}
