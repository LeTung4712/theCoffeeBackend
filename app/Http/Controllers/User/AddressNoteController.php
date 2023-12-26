<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AddressNote;
use Illuminate\Http\Request;

class AddressNoteController extends Controller
{
    public function getAddressNote(Request $request){
        $addressNote = AddressNote::where('user_id', $request->user_id)
                                    ->orderByDesc('id')
                                    ->get();
        return response([
            'address_note' => $addressNote,
        ]);
    }
}
