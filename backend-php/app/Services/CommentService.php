<?php

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Comment;
use App\Models\Product;
use App\Models\User;

class CommentService
{
    public function list(int $page, int $size): array
    {
        $query = Comment::with(['user', 'product', 'replies.user'])->orderByDesc('created_at');
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function byProduct(int $productId, int $page, int $size): array
    {
        $query = Comment::with(['user', 'replies.user'])
            ->where('product_id', $productId)
            ->whereNull('parent_comment_id')
            ->orderByDesc('created_at');
        $total = $query->count();
        $items = $query->offset($page * $size)->limit($size)->get();

        return [$items, $total];
    }

    public function find(int $id): Comment
    {
        $comment = Comment::with(['user', 'product', 'replies.user'])->find($id);

        if (!$comment) {
            throw new ResourceNotFoundException('Bình luận', 'id', $id);
        }

        return $comment;
    }

    public function create(int $productId, User $user, array $data): Comment
    {
        if (!Product::where('id', $productId)->exists()) {
            throw new ResourceNotFoundException('Sản phẩm', 'id', $productId);
        }

        $comment = Comment::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'content' => $data['content'],
            'parent_comment_id' => $data['parent_comment_id'] ?? null,
            'is_staff_reply' => in_array($user->role->name, ['ADMIN', 'STAFF'], true),
        ]);

        return $comment->load(['user', 'replies.user']);
    }

    public function reply(Comment $parent, User $user, string $content): Comment
    {
        return $this->create($parent->product_id, $user, [
            'content' => $content,
            'parent_comment_id' => $parent->id,
        ]);
    }

    public function replyForUser(Comment $parent, int $userId, string $content): Comment
    {
        $user = User::with('role')->find($userId);

        if (!$user) {
            throw new ResourceNotFoundException('Người dùng', 'id', $userId);
        }

        return $this->reply($parent, $user, $content);
    }

    public function update(int $id, User $user, string $content): Comment
    {
        $comment = $this->find($id);

        if ($comment->user_id !== $user->id && $user->role->name !== 'ADMIN') {
            throw new ForbiddenException();
        }

        $comment->update(['content' => $content]);

        return $comment->refresh()->load(['user', 'replies.user']);
    }

    public function delete(int $id, User $user): void
    {
        $comment = $this->find($id);

        if ($comment->user_id !== $user->id && $user->role->name !== 'ADMIN') {
            throw new ForbiddenException();
        }

        $comment->delete();
    }
}
