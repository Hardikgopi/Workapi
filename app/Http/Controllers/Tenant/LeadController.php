<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * List all leads (with optional filters).
     */
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('company', 'like', '%' . $request->search . '%');
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Create a new lead.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'company'     => 'nullable|string|max:255',
            'source'      => 'nullable|string|max:100',
            'status'      => 'nullable|in:new,contacted,qualified,lost,converted',
            'value'       => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string',
            'assigned_to' => 'nullable|integer',
        ]);

        $lead = Lead::create($data);

        return response()->json(['message' => 'Lead created', 'lead' => $lead], 201);
    }

    /**
     * Show a single lead.
     */
    public function show(int $id)
    {
        $lead = Lead::findOrFail($id);
        return response()->json($lead);
    }

    /**
     * Update a lead.
     */
    public function update(Request $request, int $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'company'     => 'nullable|string|max:255',
            'source'      => 'nullable|string|max:100',
            'status'      => 'nullable|in:new,contacted,qualified,lost,converted',
            'value'       => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string',
            'assigned_to' => 'nullable|integer',
        ]);

        $lead->update($data);

        return response()->json(['message' => 'Lead updated', 'lead' => $lead]);
    }

    /**
     * Delete a lead.
     */
    public function destroy(int $id)
    {
        Lead::findOrFail($id)->delete();
        return response()->json(['message' => 'Lead deleted']);
    }
}
