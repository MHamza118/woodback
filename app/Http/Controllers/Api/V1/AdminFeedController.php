<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedPostResource;
use App\Http\Resources\FeedCommentResource;
use App\Models\FeedPost;
use App\Models\FeedComment;
use App\Models\FeedLike;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminFeedController extends Controller
{
    /**
     * Get paginated feed posts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPosts(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 10);
            $page = (int) $request->query('page', 1);

            $posts = FeedPost::with(['author', 'comments.author'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Transform posts manually to avoid resource issues
            $data = $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'author' => [
                        'id' => $post->author->id,
                        'first_name' => $post->author->first_name,
                        'last_name' => $post->author->last_name,
                        'name' => $post->author->first_name . ' ' . $post->author->last_name,
                        'avatar_url' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $post->author->id,
                        'profile_image' => $post->author->profile_image,
                        'role' => 'employee',
                    ],
                    'content' => $post->content,
                    'image_url' => $post->image_url ? \Storage::disk('public')->url($post->image_url) : null,
                    'likes_count' => $post->likes_count,
                    'comments_count' => $post->comments_count,
                    'created_at' => $post->created_at->toIso8601String(),
                    'is_liked' => false,
                    'comments' => $post->comments->map(function ($comment) {
                        return [
                            'id' => $comment->id,
                            'post_id' => $comment->post_id,
                            'author' => [
                                'id' => $comment->author->id,
                                'first_name' => $comment->author->first_name,
                                'last_name' => $comment->author->last_name,
                                'name' => $comment->author->first_name . ' ' . $comment->author->last_name,
                                'avatar_url' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $comment->author->id,
                                'profile_image' => $comment->author->profile_image,
                                'role' => 'employee',
                            ],
                            'content' => $comment->content,
                            'created_at' => $comment->created_at->toIso8601String(),
                        ];
                    })->toArray(),
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch posts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch posts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new post.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPost(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
            ]);

            $admin = auth('sanctum')->user();

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imageUrl = $request->file('image')->store('feed-posts', 'public');
            }

            $post = FeedPost::create([
                'employee_id' => $admin->id,
                'content' => $validated['content'],
                'image_url' => $imageUrl,
            ]);

            $post->load(['author', 'comments.author']);

            return response()->json([
                'success' => true,
                'data' => new FeedPostResource($post),
                'message' => 'Post created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create post', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a post (admin can delete any post).
     *
     * @param FeedPost $post
     * @return JsonResponse
     */
    public function deletePost(FeedPost $post): JsonResponse
    {
        try {
            $admin = auth('sanctum')->user();

            // Log the deletion action for audit purposes
            Log::info('Admin deleted post', [
                'admin_id' => $admin->id,
                'post_id' => $post->id,
                'post_author_id' => $post->employee_id,
                'timestamp' => now(),
            ]);

            // Delete associated likes
            FeedLike::where('post_id', $post->id)->forceDelete();
            
            // Delete associated comments
            FeedComment::where('post_id', $post->id)->forceDelete();
            
            // Hard delete the post
            $post->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete post', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Like a post.
     *
     * @param FeedPost $post
     * @return JsonResponse
     */
    public function likePost(FeedPost $post): JsonResponse
    {
        try {
            $admin = auth('sanctum')->user();

            // Check if already liked
            if ($post->isLikedBy($admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post already liked',
                ], 400);
            }

            FeedLike::create([
                'post_id' => $post->id,
                'employee_id' => $admin->id,
            ]);

            $post->incrementLikesCount();

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'likes_count' => $post->likes_count,
                ],
                'message' => 'Post liked',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to like post', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to like post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unlike a post.
     *
     * @param FeedPost $post
     * @return JsonResponse
     */
    public function unlikePost(FeedPost $post): JsonResponse
    {
        try {
            $admin = auth('sanctum')->user();

            // Check if not liked
            if (!$post->isLikedBy($admin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not liked',
                ], 400);
            }

            FeedLike::where('post_id', $post->id)
                ->where('employee_id', $admin->id)
                ->delete();

            $post->decrementLikesCount();

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'likes_count' => $post->likes_count,
                ],
                'message' => 'Post unliked',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unlike post', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to unlike post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a comment to a post.
     *
     * @param FeedPost $post
     * @param Request $request
     * @return JsonResponse
     */
    public function addComment(FeedPost $post, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:250',
            ]);

            $admin = auth('sanctum')->user();

            $comment = FeedComment::create([
                'post_id' => $post->id,
                'employee_id' => $admin->id,
                'content' => $validated['content'],
            ]);

            $comment->load('author');
            $post->incrementCommentsCount();

            return response()->json([
                'success' => true,
                'data' => new FeedCommentResource($comment),
                'message' => 'Comment added',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to add comment', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a comment (admin can delete any comment).
     *
     * @param FeedComment $comment
     * @return JsonResponse
     */
    public function deleteComment(FeedComment $comment): JsonResponse
    {
        try {
            $admin = auth('sanctum')->user();

            // Log the deletion action for audit purposes
            Log::info('Admin deleted comment', [
                'admin_id' => $admin->id,
                'comment_id' => $comment->id,
                'comment_author_id' => $comment->employee_id,
                'post_id' => $comment->post_id,
                'timestamp' => now(),
            ]);

            $post = $comment->post;
            // Hard delete the comment
            $comment->forceDelete();
            $post->decrementCommentsCount();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete comment', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comments for a post.
     *
     * @param FeedPost $post
     * @param Request $request
     * @return JsonResponse
     */
    public function getComments(FeedPost $post, Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 5);
            $page = $request->query('page', 1);

            $comments = $post->comments()
                ->with('author')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => FeedCommentResource::collection($comments),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'total' => $comments->total(),
                    'per_page' => $comments->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comments', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
