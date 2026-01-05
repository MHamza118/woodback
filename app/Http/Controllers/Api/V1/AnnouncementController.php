<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Http\Resources\AnnouncementResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all announcements (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $announcements = Announcement::orderBy('created_at', 'desc')->get();

            return $this->successResponse(
                AnnouncementResource::collection($announcements),
                'Announcements retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve announcements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get active announcements for employees
     */
    public function getActive(Request $request): JsonResponse
    {
        try {
            $announcements = Announcement::active()
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse(
                AnnouncementResource::collection($announcements),
                'Active announcements retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve announcements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new announcement (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'required|in:general,urgent,event,policy',
                'start_date' => 'required|date_format:Y-m-d H:i:s',
                'end_date' => 'nullable|date_format:Y-m-d H:i:s|after:start_date',
                'is_active' => 'boolean'
            ]);

            $user = $request->user();
            $validated['created_by'] = $user->id;

            $announcement = Announcement::create($validated);

            return $this->successResponse(
                new AnnouncementResource($announcement),
                'Announcement created successfully',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific announcement
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return $this->errorResponse('Announcement not found', 404);
            }

            return $this->successResponse(
                new AnnouncementResource($announcement),
                'Announcement retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an announcement (admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return $this->errorResponse('Announcement not found', 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
                'type' => 'sometimes|in:general,urgent,event,policy',
                'start_date' => 'sometimes|date_format:Y-m-d H:i:s',
                'end_date' => 'nullable|date_format:Y-m-d H:i:s|after:start_date',
                'is_active' => 'sometimes|boolean'
            ]);

            $announcement->update($validated);

            return $this->successResponse(
                new AnnouncementResource($announcement),
                'Announcement updated successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an announcement (admin only)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return $this->errorResponse('Announcement not found', 404);
            }

            $announcement->delete();

            return $this->successResponse(
                null,
                'Announcement deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete announcement: ' . $e->getMessage(), 500);
        }
    }
}
