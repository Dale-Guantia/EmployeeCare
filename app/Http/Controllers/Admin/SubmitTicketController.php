<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class SubmitTicketController extends Controller
{
    public function show()
    {
        return view('submit-ticket', [
            'ticketCreateUrl' => backpack_url('ticket/create'),
        ]);
    }
}
