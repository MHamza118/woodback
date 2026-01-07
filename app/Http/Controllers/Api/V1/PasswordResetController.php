<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to email
     * Works for both employees and admins
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Check if email exists in employees table
        $employee = Employee::where('email', $email)->first();
        if ($employee) {
            $status = Password::broker('employees')->sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email',
                    'user_type' => 'employee'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Unable to send reset link. Please try again.'
            ], 400);
        }

        // Check if email exists in admins table
        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            $status = Password::broker('admins')->sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email',
                    'user_type' => 'admin'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Unable to send reset link. Please try again.'
            ], 400);
        }

        // Email not found in either table
        return response()->json([
            'success' => false,
            'error' => 'Email not found in our system'
        ], 404);
    }

    /**
     * Verify reset token
     * Works for both employees and admins
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $token = $request->token;
        $email = $request->email;

        // Check if email exists in employees table
        $employee = Employee::where('email', $email)->first();
        if ($employee) {
            return $this->verifyEmployeeToken($token, $email);
        }

        // Check if email exists in admins table
        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            return $this->verifyAdminToken($token, $email);
        }

        return response()->json([
            'success' => false,
            'error' => 'Email not found in our system'
        ], 404);
    }

    /**
     * Verify employee reset token
     */
    private function verifyEmployeeToken($token, $email)
    {
        $resetRecord = \DB::table('password_resets')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired reset token'
            ], 400);
        }

        if (!Hash::check($token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired reset token'
            ], 400);
        }

        if (\Carbon\Carbon::parse($resetRecord->created_at)->addMinutes(config('auth.passwords.users.expire', 60))->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'Reset token has expired'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'user_type' => 'employee'
        ]);
    }

    /**
     * Verify admin reset token
     */
    private function verifyAdminToken($token, $email)
    {
        $resetRecord = \DB::table('password_resets_admin')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired reset token'
            ], 400);
        }

        if (!Hash::check($token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired reset token'
            ], 400);
        }

        if (\Carbon\Carbon::parse($resetRecord->created_at)->addMinutes(config('auth.passwords.admins.expire', 60))->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'Reset token has expired'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'user_type' => 'admin'
        ]);
    }

    /**
     * Reset password with token
     * Works for both employees and admins
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $request->email;

        // Check if email exists in employees table
        $employee = Employee::where('email', $email)->first();
        if ($employee) {
            $status = Password::broker('employees')->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully',
                    'user_type' => 'employee'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to reset password. Token may be invalid or expired.'
            ], 400);
        }

        // Check if email exists in admins table
        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            $status = Password::broker('admins')->reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully',
                    'user_type' => 'admin'
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to reset password. Token may be invalid or expired.'
            ], 400);
        }

        return response()->json([
            'success' => false,
            'error' => 'Email not found in our system'
        ], 404);
    }
}
