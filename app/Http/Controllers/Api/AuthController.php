<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\VerifyAccountOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $otp = (string) random_int(100000, 999999);
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->role = $data['role']; // Never accept admin from a public request.
        $user->is_verified = false;
        $user->otp_code = Hash::make($otp);
        $user->otp_expires_at = now()->addMinutes(config('wasla.otp_minutes', 15));
        $user->otp_attempts = 0;
        $user->save();
        // If mail transport fails, unverified user remains; resend-otp can retry safely.
        try {
            Mail::to($user->email)->send(new VerifyAccountOtp($otp));
        } catch (Throwable $e) {
            Log::error('WASLA OTP mail transport failure', ['user_id' => $user->id, 'exception' => $e::class]);

            return response()->json(['status' => 'error', 'message' => 'Account created but email delivery failed. Use resend-otp after fixing mail settings.'], 503);
        }

        return response()->json(['status' => 'success', 'message' => 'تم إنشاء الحساب. أدخل رمز التفعيل المرسل إلى البريد الإلكتروني.'], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'otp' => ['required', 'digits:6']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || $user->is_verified || ! $user->otp_code || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'رمز التفعيل غير صحيح أو منتهي الصلاحية.'], 400);
        }
        if ($user->otp_attempts >= 5) {
            return response()->json(['status' => 'error', 'message' => 'تم تجاوز محاولات التحقق. اطلب رمزاً جديداً.'], 429);
        }
        if (! Hash::check($data['otp'], $user->otp_code)) {
            $user->increment('otp_attempts');

            return response()->json(['status' => 'error', 'message' => 'رمز التفعيل غير صحيح أو منتهي الصلاحية.'], 400);
        }
        $user->forceFill([
            'is_verified' => true, 'email_verified_at' => now(), 'otp_code' => null,
            'otp_expires_at' => null, 'otp_attempts' => 0,
        ])->save();
        $token = $user->createToken('wasla-api', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json(['status' => 'success', 'message' => 'تم تفعيل الحساب بنجاح.', 'token' => $token, 'user' => $this->safeUser($user)]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();
        // Uniform response to reduce account enumeration.
        if ($user && ! $user->is_verified) {
            $otp = (string) random_int(100000, 999999);
            $user->forceFill(['otp_code' => Hash::make($otp), 'otp_expires_at' => now()->addMinutes(15), 'otp_attempts' => 0])->save();
            try {
                Mail::to($user->email)->send(new VerifyAccountOtp($otp));
            } catch (Throwable $e) {
                Log::error('WASLA OTP resend failed', ['user_id' => $user->id, 'exception' => $e::class]);

                return response()->json(['status' => 'error', 'message' => 'Mail temporarily unavailable.'], 503);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'إذا كان الحساب بحاجة للتفعيل، فقد تم إرسال رمز جديد.']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'بيانات الدخول غير صحيحة.'], 401);
        }
        if (! $user->is_verified) {
            return response()->json(['status' => 'unverified', 'message' => 'الحساب غير مفعل، يرجى إدخال OTP.'], 403);
        }
        $token = $user->createToken('wasla-api', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json(['status' => 'success', 'token' => $token, 'user' => $this->safeUser($user)]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->safe()->only('email'));

        return response()->json([
            'status' => 'success',
            'message' => 'إذا كان البريد مسجلاً، فسيتم إرسال رابط إعادة تعيين كلمة المرور.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => 'رابط إعادة تعيين كلمة المرور غير صالح أو منتهي الصلاحية.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تغيير كلمة المرور بنجاح. سجل الدخول مرة أخرى.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'كلمة المرور الحالية غير صحيحة.',
            ], 422);
        }

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تغيير كلمة المرور. سجل الدخول مرة أخرى.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->safeUser($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['status' => 'success', 'message' => 'تم تسجيل الخروج.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج من جميع الأجهزة.',
        ]);
    }

    private function safeUser(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role', 'is_verified']);
    }
}
