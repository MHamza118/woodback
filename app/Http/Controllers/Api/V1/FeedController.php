<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedPostResource;
use App\Http\Resources\FeedCommentResource;
use App\Models\FeedPost;
use App\Models\FeedComment;
use App\Models\FeedLike;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class FeedController extends Controller
{
    /**
     * Get paginated feed posts.
     */
    public function getPosts(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 10);
            $page = (int) $request->query('page', 1);

            $posts = FeedPost::with([
                'comments',
                'likes'
            ])
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
            \Log::error('Failed to fetch posts', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch posts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new post.
     */
    public function createPost(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:500',
                'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:5120',
            ]);

            $user = auth('sanctum')->user();

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imageUrl = $request->file('image')->store('feed-posts', 'public');
            }

            $post = FeedPost::create([
                'author_type' => 'employee',
                'author_id' => $user->id,
                'content' => $validated['content'],
                'image_url' => $imageUrl,
            ]);

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
     * Delete a post.
     */
    public function deletePost(FeedPost $post): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();

            // Check if the user owns the post
            if ($post->author_type !== 'employee' || $post->author_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this post',
                ], 403);
            }

            FeedLike::where('post_id', $post->id)->delete();
            FeedComment::where('post_id', $post->id)->delete();
            $post->delete();

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
     */
    public function likePost(FeedPost $post): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();

            if ($post->isLikedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post already liked',
                ], 400);
            }

            FeedLike::create([
                'post_id' => $post->id,
                'user_type' => 'employee',
                'user_id' => $user->id,
            ]);

            $post->incrementLikesCount();

            return response()->json([
                'success' => true,
                'data' => ['post_id' => $post->id, 'likes_count' => $post->likes_count],
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
     */
    public function unlikePost(FeedPost $post): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();

            if (!$post->isLikedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not liked',
                ], 400);
            }

            FeedLike::where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->where('user_type', 'employee')
                ->delete();

            $post->decrementLikesCount();

            return response()->json([
                'success' => true,
                'data' => ['post_id' => $post->id, 'likes_count' => $post->likes_count],
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
     */
    public function addComment(FeedPost $post, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|max:250',
            ]);

            $user = auth('sanctum')->user();

            $comment = FeedComment::create([
                'post_id' => $post->id,
                'author_type' => 'employee',
                'author_id' => $user->id,
                'content' => $validated['content'],
            ]);

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
     * Delete a comment.
     */
    public function deleteComment(FeedComment $comment): JsonResponse
    {
        try {
            $user = auth('sanctum')->user();

            // Check if the user owns the comment
            if ($comment->author_type !== 'employee' || $comment->author_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this comment',
                ], 403);
            }

            $post = $comment->post;
            $comment->delete();
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
     */
    public function getComments(FeedPost $post, Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 5);
            $page = (int) $request->query('page', 1);

            $comments = $post->comments()
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
