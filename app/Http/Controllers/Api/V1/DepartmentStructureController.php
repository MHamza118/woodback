<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DepartmentStructure;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentStructureController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get the complete department structure
     */
    public function index(): JsonResponse
    {
        try {
            $structure = [
                'FOH' => [
                    'id' => 'FOH',
                    'name' => 'Front of House',
                    'description' => 'Customer-facing operations and service',
                    'color' => 'purple',
                    'areas' => []
                ],
                'BOH' => [
                    'id' => 'BOH',
                    'name' => 'Back of House',
                    'description' => 'Kitchen and food preparation operations',
                    'color' => 'orange',
                    'areas' => []
                ]
            ];

            // Get areas from database
            $fohAreas = DepartmentStructure::getAreasForDepartment('FOH');
            $bohAreas = DepartmentStructure::getAreasForDepartment('BOH');

            foreach ($fohAreas as $area) {
                $structure['FOH']['areas'][] = [
                    'id' => $area->area_id,
                    'name' => $area->area_name,
                    'description' => $area->area_description,
                    'roles' => $area->roles
                ];
            }

            foreach ($bohAreas as $area) {
                $structure['BOH']['areas'][] = [
                    'id' => $area->area_id,
                    'name' => $area->area_name,
                    'description' => $area->area_description,
                    'roles' => $area->roles
                ];
            }

            return $this->successResponse($structure, 'Department structure retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve department structure: ' . $e->getMessage());
        }
    }

    /**
     * Add or update an area
     */
    public function updateArea(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => 'required|in:FOH,BOH',
            'area_id' => 'required|string|max:255',
            'area_name' => 'required|string|max:255',
            'area_description' => 'nullable|string',
            'roles' => 'array',
            'roles.*.id' => 'required|string',
            'roles.*.name' => 'required|string',
            'roles.*.description' => 'nullable|string'
        ]);

        try {
            $area = DepartmentStructure::updateArea(
                $request->department_id,
                $request->area_id,
                $request->area_name,
                $request->area_description,
                $request->roles
            );

            return $this->successResponse($area, 'Area updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update area: ' . $e->getMessage());
        }
    }

    /**
     * Add a role to an area
     */
    public function addRole(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => 'required|in:FOH,BOH',
            'area_id' => 'required|string',
            'role' => 'required|array',
            'role.id' => 'required|string',
            'role.name' => 'required|string',
            'role.description' => 'nullable|string'
        ]);

        try {
            $area = DepartmentStructure::where('department_id', $request->department_id)
                                     ->where('area_id', $request->area_id)
                                     ->first();

            if (!$area) {
                return $this->errorResponse('Area not found', 404);
            }

            $roles = $area->roles;
            
            // Check if role already exists
            $existingRoleIndex = array_search($request->role['id'], array_column($roles, 'id'));
            
            if ($existingRoleIndex !== false) {
                // Update existing role
                $roles[$existingRoleIndex] = $request->role;
            } else {
                // Add new role
                $roles[] = $request->role;
            }

            $area->roles = $roles;
            $area->save();

            return $this->successResponse($area, 'Role added successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add role: ' . $e->getMessage());
        }
    }

    /**
     * Remove a role from an area
     */
    public function removeRole(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => 'required|in:FOH,BOH',
            'area_id' => 'required|string',
            'role_id' => 'required|string'
        ]);

        try {
            $area = DepartmentStructure::where('department_id', $request->department_id)
                                     ->where('area_id', $request->area_id)
                                     ->first();

            if (!$area) {
                return $this->errorResponse('Area not found', 404);
            }

            $roles = $area->roles;
            $roles = array_filter($roles, function($role) use ($request) {
                return $role['id'] !== $request->role_id;
            });

            $area->roles = array_values($roles); // Re-index array
            $area->save();

            return $this->successResponse($area, 'Role removed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove role: ' . $e->getMessage());
        }
    }
}