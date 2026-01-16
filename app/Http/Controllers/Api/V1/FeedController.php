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

class FeedController extends Controller
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
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $posts = FeedPost::with(['author', 'comments.author'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => FeedPostResource::collection($posts),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'total' => $posts->total(),
                    'per_page' => $posts->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
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

            $employee = auth('sanctum')->user();

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imageUrl = $request->file('image')->store('feed-posts', 'public');
            }

            $post = FeedPost::create([
                'employee_id' => $employee->id,
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a post.
     *
     * @param FeedPost $post
     * @return JsonResponse
     */
    public function deletePost(FeedPost $post): JsonResponse
    {
        try {
            $employee = auth('sanctum')->user();

            // Check if the employee owns the post
            if ($post->employee_id !== $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this post',
                ], 403);
            }

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
            $employee = auth('sanctum')->user();

            // Check if already liked
            if ($post->isLikedBy($employee)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post already liked',
                ], 400);
            }

            FeedLike::create([
                'post_id' => $post->id,
                'employee_id' => $employee->id,
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
            $employee = auth('sanctum')->user();

            // Check if not liked
            if (!$post->isLikedBy($employee)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not liked',
                ], 400);
            }

            FeedLike::where('post_id', $post->id)
                ->where('employee_id', $employee->id)
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

            $employee = auth('sanctum')->user();

            $comment = FeedComment::create([
                'post_id' => $post->id,
                'employee_id' => $employee->id,
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a comment.
     *
     * @param FeedComment $comment
     * @return JsonResponse
     */
    public function deleteComment(FeedComment $comment): JsonResponse
    {
        try {
            $employee = auth('sanctum')->user();

            // Check if the employee owns the comment
            if ($comment->employee_id !== $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment',
                ], 403);
            }

            $post = $comment->post;
            // Hard delete the comment
            $comment->forceDelete();
            $post->decrementCommentsCount();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
