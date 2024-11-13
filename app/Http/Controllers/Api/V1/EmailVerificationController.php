<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    public function sendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 200);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link has been sent to your email'
        ], 200);
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'exists:users,id'],
            'hash' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->id);

        if (!hash_equals((string)$request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'error' => 'Invalid verification token'
            ], 403);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email has been verified'
        ], 200);
    }
}
