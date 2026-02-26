<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Employee;

class CheckEmployeeStatus
{
    /**
     * Handle an incoming request.
     * Check if the authenticated employee is paused or inactive.
     * If so, revoke their token and return 401.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Only check if user is an employee (not admin or customer)
        if ($user && $user instanceof Employee) {
            // Refresh employee data to get current status
            $employee = Employee::find($user->id);
            
            if (!$employee) {
                // Employee not found - revoke token and return error
                $request->user()->currentAccessToken()->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Employee account not found. You have been logged out.',
                    'force_logout' => true
                ], 401);
            }
            
            if ($employee->isPaused()) {
                $request->user()->currentAccessToken()->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been paused. Please contact your manager. You have been logged out.',
                    'force_logout' => true,
                    'status' => 'paused'
                ], 401);
            }
            
            if ($employee->isInactive()) {
                $request->user()->currentAccessToken()->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact your manager. You have been logged out.',
                    'force_logout' => true,
                    'status' => 'inactive'
                ], 401);
            }
        }
        
        return $next($request);
    }
}
