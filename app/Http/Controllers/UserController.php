<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    //login function
    public function loginindex()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'username' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        # return also style and script datatables from vuexy and also sweetalert2
        $headstyle = '<link rel="stylesheet" href="/assets/vendor/libs/sweetalert2/sweetalert2.css">';
        $headscript = '<script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
                <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
                <script src="/assets/vendor/libs/datatables/jquery.dataTables.js"></script>
                <script src="/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
                <script src="/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>
                <script src="/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.js"></script>';

        return view('_app.app', [
            'content' => 'user.index',
            'headerdata' => ['pagetitle' => 'User Management', 'headstyle' => $headstyle, 'headscript' => $headscript],
            'sidenavdata' => ['active' => 'users'],
        ]);
    }


public function tabledata(Request $request)
    {

        if ($request->filled('id')) {
            $user = User::query()
                ->select(['id', 'username', 'name', 'email', 'role', 'created_at'])
                ->find($request->integer('id'));

            if (! $user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            return response()->json(['data' => $this->formatUser($user)]);
        }

        $users = User::query()
            ->select(['id', 'username', 'name', 'email', 'role', 'created_at'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json(['data' => $users]);
    }

    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => optional($user->created_at)->toDateTimeString(),
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //new user creation only by admin
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'username' => 'required|string|unique:users,username',
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,annotator,validator',
        ]);

        $user = User::create([
            'username' => $request->input('username'),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
            'role' => $request->input('role'),
        ]);
        return back()->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //user update only by admin
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'required|string|unique:users,username,'.$user->id,
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:admin,annotator,validator',
        ]);

        $user->username = $request->input('username');
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->role = $request->input('role');

        // Update password only if provided
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'string|min:8',
            ]);
            $user->password = bcrypt($request->input('password'));
        }

        $user->save();

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
