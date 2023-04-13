<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where(['email' => $request['email']])->first();

        if (!$user || !Hash::check(strval($request['password']), $user->password)) {
            return response()->json('Email or Password is incorrect');
        }

        $userOtp = $this->generateOTP($user);
//        $userOtp->sendSMS($user->mobile_no);

        return response()->json('OTP code sent to your mobile, USER_ID: ' . $userOtp->user_id);


    }

    public function generateOTP(User $user)
    {
        $userOtp = UserOtp::where('user_id', $user->id)->first();

        if ($userOtp && now()->isBefore($userOtp->expire_at)) {
            return $userOtp;
        } else if ($userOtp && !now()->isBefore($userOtp->expire_at)) {
            $userOtp->delete();
        }

        return UserOtp::create([
            'user_id' => $user->id,
            'otp' => rand(1234, 9999),
            'expire_at' => now()->addMinute(20)
        ]);
    }

    public function loginSmsCode(Request $request)
    {
        $request->validate([
            'otp' => 'required',
            'user_id' => ['required', Rule::exists('users', 'id')],
        ]);

        $userOtp = UserOtp::where('user_id', $request->user_id)
            ->where('otp', $request->otp)->first();

        if (!$userOtp) {
            return response()->json('Error, Your OTP is not Correct');
        } else if (now()->isAfter($userOtp->expire_at)) {
            $userOtp->delete();
            return response()->json('Error, Your OTP has been Expired');
        }


        $user = User::whereId($request->user_id)->first();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json('Code Confirmed, Token: ' . $token);
    }

}
