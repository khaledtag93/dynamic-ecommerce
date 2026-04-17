<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Category;


class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth'); // Require login for all methods
    }

    public function index()
    {
        $user = Auth::user();
        if ($user->role_as == 1) {
            // Admin: Redirect to admin/category
            // return redirect('admin/category')->with('status', 'Welcome to the admin dashboard, ' . $user->name . '!');
           //  return redirect('admin/category')->with('status','welcome to dashboard khaled');
           return view('admin.dashboard');
        } else {
            // Normal user: Redirect to home or a user dashboard
            $categories = Category::orderBy('id', 'desc')->paginate(10);
       return view('frontend.index', compact('categories'));
    }
}
}