<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User; // Model pengguna
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role' => 'visitor', // Pengguna publik defaultnya 'visitor'
            ]);

            // Jika model User Anda mengimplementasikan MustVerifyEmail,
            // Laravel akan otomatis mengirim email verifikasi di sini.

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
            return response()->json([
                'message' => 'Data yang diberikan tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Pertimbangkan untuk log error ini: Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Registrasi gagal. Terjadi kesalahan server.',
                // 'error' => $e->getMessage(), // Mungkin tidak ingin diekspos ke klien
            ], 500);
        }
    }

    /**
     * Menangani login pengguna.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $user = Auth::user();

        // Jika pengguna belum memverifikasi emailnya dan aplikasi memerlukan verifikasi
        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail()) {
            // Opsional: Logout pengguna dan kirim pesan untuk verifikasi
            // Auth::logout(); // Atau jangan login-kan dan jangan buat token
            return response()->json([
                'message' => 'Email Anda belum diverifikasi. Silakan cek email Anda untuk tautan verifikasi.',
                'email_not_verified' => true, // Flag untuk klien
            ], 403); // Forbidden
        }

        $tokenName = 'api-token-' . $user->id;
        // $user->tokens()->where('name', $tokenName)->delete(); // Hapus token lama dengan nama yang sama (jika perlu)
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            ],
            'token_type' => 'Bearer',
            'access_token' => $token,
        ]);
    }

    /**
     * Menangani logout pengguna.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }

    /**
     * Menangani permintaan lupa password.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __('passwords.sent')], 200);
        }

        return response()->json(['message' => __('passwords.user')], 400);
    }

    /**
     * Menangani permintaan reset password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __('passwords.reset')], 200);
        }

        return response()->json(['message' => __('passwords.token')], 400);
    }

    /**
     * Mengirim notifikasi verifikasi email.
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah diverifikasi.'], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Tautan verifikasi telah dikirim ke email Anda.']);
    }

    /**
     * Menandai email pengguna yang terautentikasi sebagai terverifikasi.
     */
    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah diverifikasi sebelumnya.'], 200);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // Untuk API, kembalikan respons JSON. Redirect lebih cocok untuk web.
        return response()->json(['message' => 'Email berhasil diverifikasi.']);
    }

    /**
     * Mendapatkan detail pengguna yang terautentikasi.
     */
    public function user(Request $request): JsonResponse
    {
        // Mengembalikan data user yang relevan, bisa disaring jika perlu
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'created_at' => $user->created_at->toIso8601String(),
            // 'initials' => $user->initials, // Jika Anda ingin menyertakan accessor
        ]);
    }
}
