<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('related_type') && $request->filled('related_id')) {
            $query->where('related_type', $request->related_type)
                  ->where('related_id', $request->related_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $user = $request->attributes->get('tenant_user');

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'priority'     => 'nullable|in:low,medium,high',
            'status'       => 'nullable|in:todo,in_progress,done',
            'due_date'     => 'nullable|date',
            'related_type' => 'nullable|string|in:lead,ticket,contact',
            'related_id'   => 'nullable|integer',
            'assigned_to'  => 'nullable|integer',
        ]);

        $data['created_by'] = $user->id;

        $task = Task::create($data);

        return response()->json(['message' => 'Task created', 'task' => $task], 201);
    }

    public function show(int $id)
    {
        return response()->json(Task::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $task = Task::findOrFail($id);

        $data = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'priority'     => 'nullable|in:low,medium,high',
            'status'       => 'nullable|in:todo,in_progress,done',
            'due_date'     => 'nullable|date',
            'related_type' => 'nullable|string|in:lead,ticket,contact',
            'related_id'   => 'nullable|integer',
            'assigned_to'  => 'nullable|integer',
        ]);

        $task->update($data);

        return response()->json(['message' => 'Task updated', 'task' => $task]);
    }

    public function destroy(int $id)
    {
        Task::findOrFail($id)->delete();
        return response()->json(['message' => 'Task deleted']);
    }
}
