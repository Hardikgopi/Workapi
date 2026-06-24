<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query();

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('company', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'company'     => 'nullable|string|max:255',
            'job_title'   => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:500',
            'city'        => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
            'assigned_to' => 'nullable|integer',
        ]);

        $contact = Contact::create($data);

        return response()->json(['message' => 'Contact created', 'contact' => $contact], 201);
    }

    public function show(int $id)
    {
        return response()->json(Contact::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $contact = Contact::findOrFail($id);

        $data = $request->validate([
            'first_name'  => 'sometimes|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'company'     => 'nullable|string|max:255',
            'job_title'   => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:500',
            'city'        => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
            'assigned_to' => 'nullable|integer',
        ]);

        $contact->update($data);

        return response()->json(['message' => 'Contact updated', 'contact' => $contact]);
    }

    public function destroy(int $id)
    {
        Contact::findOrFail($id)->delete();
        return response()->json(['message' => 'Contact deleted']);
    }
}
