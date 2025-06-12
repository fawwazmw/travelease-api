<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User; // Model pengguna
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log; // Menggunakan Log facade
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class AuthController extends Controller
{
    /**
     * Menangani registrasi pengguna.
     */
    public function register(Request $request): JsonResponse
    {
        Log::info('API Register: Attempting registration.', ['email' => $request->email]);
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);
            Log::info('API Register: Validation successful.', ['email' => $request->email]);

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role' => 'visitor', // Pengguna publik defaultnya 'visitor'
            ]);
            Log::info('API Register: User created successfully.', ['user_id' => $user->id, 'email' => $user->email]);

            // Jika model User Anda mengimplementasikan MustVerifyEmail,
            // Laravel akan otomatis mengirim email verifikasi saat event 'created' User.
            // Jika tidak otomatis atau Anda ingin memastikan pengiriman dari sini:
            // if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
            //     $user->sendEmailVerificationNotification();
            // }

            return response()->json([
                'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi jika diperlukan, lalu login.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at->toIso8601String(),
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
                ],
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('API Register: Validation failed.', ['errors' => $e->errors(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Register: Registration failed due to server error.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Registrasi gagal. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Menangani login pengguna.
     */
    public function login(Request $request): JsonResponse
    {
        Log::info('API Login: Attempting login.', ['email' => $request->email]);
        try {
            $credentials = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);
            Log::info('API Login: Credentials validated.', ['email' => $request->email]);

            if (!Auth::attempt($credentials)) {
                Log::warning('API Login: Invalid credentials.', ['email' => $request->email]);
                return response()->json([
                    'message' => 'Email atau password salah.'
                ], 401);
            }
            Log::info('API Login: Auth::attempt successful.', ['email' => $request->email]);

            $user = Auth::user();
            if (!$user) { // Safety check
                Log::error('API Login: Auth::user() returned null after successful attempt.', ['email' => $request->email]);
                return response()->json(['message' => 'Gagal mendapatkan data pengguna setelah login.'], 500);
            }

            if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
                Log::warning('API Login: Email not verified.', ['user_id' => $user->id, 'email' => $user->email]);
                return response()->json([
                    'message' => 'Email Anda belum diverifikasi. Silakan cek email Anda untuk tautan verifikasi.',
                    'email_not_verified' => true,
                ], 403);
            }
            Log::info('API Login: Email verification check passed or not required.', ['user_id' => $user->id]);

            $tokenName = 'api-token-' . $user->id;
            // Hapus semua token lama pengguna ini agar hanya ada satu token aktif yang baru
            $user->tokens()->delete();
            $token = $user->createToken($tokenName)->plainTextToken;
            Log::info('API Login: Token created successfully.', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Login berhasil.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
                    'created_at' => $user->created_at->toIso8601String(),
                ],
                'token_type' => 'Bearer',
                'access_token' => $token,
            ]);

        } catch (ValidationException $e) {
            Log::warning('API Login: Validation failed.', ['errors' => $e->errors(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API Login: Login failed due to server error.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Login gagal. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Menangani logout pengguna.
     */
    public function logout(Request $request): JsonResponse
    {
        Log::info('API Logout: Attempting logout.', ['user_id' => $request->user()->id]);
        try {
            $request->user()->currentAccessToken()->delete();
            Log::info('API Logout: Token deleted successfully.', ['user_id' => $request->user()->id]);
            return response()->json([
                'message' => 'Logout berhasil.'
            ]);
        } catch (\Exception $e) {
            Log::error('API Logout: Logout failed.', ['error' => $e->getMessage(), 'user_id' => $request->user() ? $request->user()->id : 'unknown']);
            return response()->json([
                'message' => 'Logout gagal. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Menangani permintaan lupa password.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        Log::info('API ForgotPassword: Attempting to send reset link.', ['email' => $request->email]);
        try {
            $request->validate(['email' => 'required|email|exists:users,email']);
            Log::info('API ForgotPassword: Email validated.', ['email' => $request->email]);

            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                Log::info('API ForgotPassword: Reset link sent.', ['email' => $request->email]);
                return response()->json(['message' => __('passwords.sent')], 200);
            }

            Log::warning('API ForgotPassword: Failed to send reset link.', ['email' => $request->email, 'status' => $status]);
            return response()->json(['message' => __('passwords.user')], 400);

        } catch (ValidationException $e) {
            Log::warning('API ForgotPassword: Validation failed.', ['errors' => $e->errors(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API ForgotPassword: Error sending reset link.', ['error' => $e->getMessage(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Gagal mengirim tautan reset password. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Menangani permintaan reset password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        Log::info('API ResetPassword: Attempting to reset password.', ['email' => $request->email]);
        try {
            $validatedData = $request->validate([
                'token' => 'required|string',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);
            Log::info('API ResetPassword: Data validated.', ['email' => $request->email]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));
                    $user->save();
                    event(new PasswordReset($user));
                    Log::info('API ResetPassword: Password reset successful for user.', ['user_id' => $user->id]);
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json(['message' => __('passwords.reset')], 200);
            }

            Log::warning('API ResetPassword: Failed to reset password.', ['email' => $request->email, 'status' => $status]);
            return response()->json(['message' => __('passwords.token')], 400);

        } catch (ValidationException $e) {
            Log::warning('API ResetPassword: Validation failed.', ['errors' => $e->errors(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API ResetPassword: Error resetting password.', ['error' => $e->getMessage(), 'email' => $request->email]);
            return response()->json([
                'message' => 'Gagal mereset password. Terjadi kesalahan server.',
            ], 500);
        }
    }

    /**
     * Mengirim notifikasi verifikasi email.
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        Log::info('API SendVerificationEmail: Request received.', ['user_id' => $request->user()->id]);
        if ($request->user()->hasVerifiedEmail()) {
            Log::info('API SendVerificationEmail: Email already verified.', ['user_id' => $request->user()->id]);
            return response()->json(['message' => 'Email sudah diverifikasi.'], 400);
        }

        $request->user()->sendEmailVerificationNotification();
        Log::info('API SendVerificationEmail: Verification link sent.', ['user_id' => $request->user()->id]);
        return response()->json(['message' => 'Tautan verifikasi telah dikirim ke email Anda.']);
    }

    /**
     * Menandai email pengguna yang terautentikasi sebagai terverifikasi.
     */
    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        Log::info('API VerifyEmail: Request received.', ['user_id' => $request->user()->id]);
        if ($request->user()->hasVerifiedEmail()) {
            Log::info('API VerifyEmail: Email already verified.', ['user_id' => $request->user()->id]);
            return response()->json(['message' => 'Email sudah diverifikasi sebelumnya.'], 200);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }
        Log::info('API VerifyEmail: Email marked as verified.', ['user_id' => $request->user()->id]);
        return response()->json(['message' => 'Email berhasil diverifikasi.']);
    }

    /**
     * Mendapatkan detail pengguna yang terautentikasi.
     */
    public function user(Request $request): JsonResponse
    {
        Log::info('API User: Fetching authenticated user details.', ['user_id' => $request->user()->id]);
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }
}
