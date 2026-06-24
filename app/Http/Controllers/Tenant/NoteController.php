<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Note::query();

        if ($request->filled('related_type') && $request->filled('related_id')) {
            $query->where('related_type', $request->related_type)
                  ->where('related_id', $request->related_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $user = $request->attributes->get('tenant_user');

        $data = $request->validate([
            'content'      => 'required|string',
            'related_type' => 'nullable|string|in:lead,ticket,contact,task',
            'related_id'   => 'nullable|integer',
        ]);

        $data['created_by'] = $user->id;

        $note = Note::create($data);

        return response()->json(['message' => 'Note created', 'note' => $note], 201);
    }

    public function show(int $id)
    {
        return response()->json(Note::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $note = Note::findOrFail($id);

        $data = $request->validate([
            'content'      => 'sometimes|string',
            'related_type' => 'nullable|string|in:lead,ticket,contact,task',
            'related_id'   => 'nullable|integer',
        ]);

        $note->update($data);

        return response()->json(['message' => 'Note updated', 'note' => $note]);
    }

    public function destroy(int $id)
    {
        Note::findOrFail($id)->delete();
        return response()->json(['message' => 'Note deleted']);
    }
}
