<?php

namespace App\Controllers;

use Reno\Http\Request;
use Reno\Http\Response;

/**
 * HomeController
 * 
 * Handles home page and general pages
 */
class HomeController
{
    /**
     * Display the home page
     */
    public function index()
    {
        return view('welcome', [
            'title' => 'Welcome to RENOPHP',
            'message' => 'Start building your amazing application!'
        ]);
    }

    /**
     * Display the about page
     */
    public function about()
    {
        return view('pages.about', [
            'title' => 'About RENOPHP'
        ]);
    }

    /**
     * Display the contact page
     */
    public function contact()
    {
        return view('pages.contact', [
            'title' => 'Contact Us'
        ]);
    }

    /**
     * Handle contact form submission
     */
    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|min:10'
        ]);

        // TODO: Send email or save to database
        
        return Response::json([
            'success' => true,
            'message' => 'Thank you for contacting us!'
        ]);
    }
}
