<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class RemoteStyleUpdate implements ShouldBroadcastNow

class RemoteStyleController extends Controller
{
    public function emisor()
    {
        return view('emisor');
    }

    public function receptor()
    {
        return view('receptor');
    }

    public function emitir(Request $request)
    {
        $data = $request->validate([
            'newText' => ['required', 'string'],
            'newStyle' => ['nullable', 'string'],
        ]);

        broadcast(new RemoteStyleUpdate(
            $data['newText'],
            $data['newStyle'] ?? ''
        ));

        return back()->with('ok', 'Mensaje emitido correctamente.');
    }
}