<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);
        $user = User::create($input);
        Auth::login($user);
        $request->session()->regenerate();
        return response()->json($user->only('id', 'email'), 201);
    }

    public function login(Request $request)
    {
        $input = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (!Auth::attempt($input)) {
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        }
        $request->session()->regenerate();
        return response()->json($request->user()->only('id', 'email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->noContent();
    }
}
