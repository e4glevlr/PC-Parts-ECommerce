<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Comments\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\ApiResponseService;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController
{
    public function __construct(private ApiResponseService $response, private CommentService $commentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->commentService->list($page, $size);
        $content = CommentResource::collection($items)->resolve($request);

        return $this->response->success('OK', $this->response->paginated($content, $total, $page, $size));
    }

    public function byProduct(Request $request, int $product): JsonResponse
    {
        $page = max((int) $request->query('page', 0), 0);
        $size = min(max((int) $request->query('size', 20), 1), 100);
        [$items, $total] = $this->commentService->byProduct($product, $page, $size);
        $content = CommentResource::collection($items)->resolve($request);

        return $this->response->success('OK', array_merge($this->response->paginated($content, $total, $page, $size), ['total_elements' => $total]));
    }

    public function show(Request $request, int $comment): JsonResponse
    {
        return $this->response->success('OK', (new CommentResource($this->commentService->find($comment)))->toArray($request));
    }

    public function store(CommentRequest $request, int $product): JsonResponse
    {
        $comment = $this->commentService->create($product, $request->attributes->get('current_user'), $request->validated());

        return $this->response->success('Thêm bình luận thành công', (new CommentResource($comment))->toArray($request), 201);
    }

    public function reply(CommentRequest $request, int $comment): JsonResponse
    {
        $reply = $this->commentService->reply($this->commentService->find($comment), $request->attributes->get('current_user'), $request->validated('content'));

        return $this->response->success('Thêm bình luận thành công', (new CommentResource($reply))->toArray($request), 201);
    }

    public function replyForUser(CommentRequest $request, int $comment, int $user): JsonResponse
    {
        $reply = $this->commentService->replyForUser($this->commentService->find($comment), $user, $request->validated('content'));

        return $this->response->success('Thêm bình luận thành công', (new CommentResource($reply))->toArray($request), 201);
    }

    public function update(CommentRequest $request, int $comment): JsonResponse
    {
        $updated = $this->commentService->update($comment, $request->attributes->get('current_user'), $request->validated('content'));

        return $this->response->success('Cập nhật bình luận thành công', (new CommentResource($updated))->toArray($request));
    }

    public function destroy(Request $request, int $comment): JsonResponse
    {
        $this->commentService->delete($comment, $request->attributes->get('current_user'));

        return $this->response->success('Xóa bình luận thành công');
    }
}
