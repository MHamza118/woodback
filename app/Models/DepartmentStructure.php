<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentStructure extends Model
{
    use HasFactory;

    protected $table = 'department_structure';

    protected $fillable = [
        'department_id',
        'area_id',
        'area_name',
        'area_description',
        'roles'
    ];

    protected $casts = [
        'roles' => 'array'
    ];

    /**
     * Get all areas for a department
     */
    public static function getAreasForDepartment($departmentId)
    {
        return self::where('department_id', $departmentId)->get();
    }

    /**
     * Get roles for a specific area
     */
    public static function getRolesForArea($departmentId, $areaId)
    {
        $area = self::where('department_id', $departmentId)
                   ->where('area_id', $areaId)
                   ->first();
        
        return $area ? $area->roles : [];
    }

    /**
     * Add or update an area
     */
    public static function updateArea($departmentId, $areaId, $areaName, $areaDescription, $roles)
    {
        return self::updateOrCreate(
            [
                'department_id' => $departmentId,
                'area_id' => $areaId
            ],
            [
                'area_name' => $areaName,
                'area_description' => $areaDescription,
                'roles' => $roles
            ]
        );
    }
}