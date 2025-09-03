<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSendRequest;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Log;


class ContactController extends Controller
{
    public function send(ContactSendRequest $request) // можно и без FormRequest, как у тебя
{
    $data = $request->validated(); // или $request->only([...])

    $msg = ContactMessage::create([
        'first_name' => $data['firstName'],
        'last_name'  => $data['lastName'],
        'email'      => $data['email'],
        'message'    => $data['message'],
        'ip'         => $request->ip(),
        'user_agent' => (string) $request->userAgent(),
    ]);

    try {
        // можно queue() вместо send()
        Mail::to('shadcnpetproject@gmail.com')->send(new ContactFormMail($data));
        return back()->with('success', 'Message sent successfully!');
    } catch (\Throwable $e) {
        Log::error('Contact mail failed', ['id'=>$msg->id, 'error'=>$e->getMessage()]);
        return back()->with('error', 'Saved in admin, but email failed. We will check it.');
    }
}
}
