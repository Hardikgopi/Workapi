<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('ticket_id')) {
            $query->where('ticket_id', $request->ticket_id);
        }

        if ($request->filled('reference_id')) {
            $query->where('reference_id', $request->reference_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $tenantUser = $request->attributes->get('tenant_user');

        $data = $request->validate([
            'amount' => 'required|integer|min:100',
            'currency' => 'nullable|string|max:10',
            'accept_partial' => 'nullable|boolean',
            'first_min_partial_amount' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'ticket_id' => 'nullable|integer',
            'customer' => 'nullable|array',
            'customer.name' => 'nullable|string|max:255',
            'customer.email' => 'nullable|email|max:255',
            'customer.contact' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255', // backward compatible
            'customer_email' => 'nullable|email|max:255', // backward compatible
            'customer_contact' => 'nullable|string|max:20', // backward compatible
            'notify' => 'nullable|array',
            'notify.sms' => 'nullable|boolean',
            'notify.email' => 'nullable|boolean',
            'reminder_enable' => 'nullable|boolean',
            'notes' => 'nullable|array',
            'callback_url' => 'nullable|url|max:500',
            'callback_method' => 'nullable|in:get,post',
            'expire_by' => 'nullable|integer|min:1',
        ]);

        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (empty($keyId) || empty($keySecret)) {
            return response()->json([
                'message' => 'Razorpay keys are missing. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in .env',
            ], 500);
        }

        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'INR',
            'description' => $data['description'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'accept_partial' => (bool) ($data['accept_partial'] ?? false),
            'notify' => [
                'sms' => (bool) data_get($data, 'notify.sms', true),
                'email' => (bool) data_get($data, 'notify.email', true),
            ],
            'reminder_enable' => (bool) ($data['reminder_enable'] ?? true),
        ];

        $callbackUrl = $data['callback_url'] ?? config('services.razorpay.callback_url');
        if (!empty($callbackUrl)) {
            $payload['callback_url'] = $callbackUrl;
            $payload['callback_method'] = $data['callback_method']
                ?? config('services.razorpay.callback_method', 'get');
        }

        if (!empty($data['first_min_partial_amount'])) {
            $payload['first_min_partial_amount'] = $data['first_min_partial_amount'];
        }

        if (!empty($data['expire_by'])) {
            $payload['expire_by'] = $data['expire_by'];
        }

        if (!empty($data['notes'])) {
            $payload['notes'] = $data['notes'];
        }

        $customerName = data_get($data, 'customer.name', $data['customer_name'] ?? null);
        $customerEmail = data_get($data, 'customer.email', $data['customer_email'] ?? null);
        $customerContact = data_get($data, 'customer.contact', $data['customer_contact'] ?? null);

        if (!empty($customerName) || !empty($customerEmail) || !empty($customerContact)) {
            $payload['customer'] = array_filter([
                'name' => $customerName,
                'email' => $customerEmail,
                'contact' => $customerContact,
            ]);
        }

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post('https://api.razorpay.com/v1/payment_links', $payload);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Failed to create payment link',
                'razorpay_error' => $response->json(),
            ], $response->status());
        }

        $rp = $response->json();

        $payment = Payment::create([
            'razorpay_payment_link_id' => $rp['id'],
            'reference_id' => $rp['reference_id'] ?? ($data['reference_id'] ?? null),
            'ticket_id' => $data['ticket_id'] ?? null,
            'created_by' => $tenantUser->id ?? null,
            'amount' => $rp['amount'] ?? $data['amount'],
            'amount_paid' => $rp['amount_paid'] ?? 0,
            'amount_due' => $rp['amount_due'] ?? (($rp['amount'] ?? $data['amount']) - ($rp['amount_paid'] ?? 0)),
            'currency' => $rp['currency'] ?? ($data['currency'] ?? 'INR'),
            'description' => $rp['description'] ?? ($data['description'] ?? null),
            'customer_name' => $rp['customer']['name'] ?? $customerName,
            'customer_email' => $rp['customer']['email'] ?? $customerEmail,
            'customer_contact' => $rp['customer']['contact'] ?? $customerContact,
            'short_url' => $rp['short_url'] ?? null,
            'status' => $rp['status'] ?? 'created',
            'expires_at' => !empty($rp['expire_by']) ? now()->setTimestamp((int) $rp['expire_by']) : null,
            'paid_at' => !empty($rp['paid_at']) ? now()->setTimestamp((int) $rp['paid_at']) : null,
            'cancelled_at' => !empty($rp['cancelled_at']) ? now()->setTimestamp((int) $rp['cancelled_at']) : null,
            'expired_at' => !empty($rp['expired_at']) ? now()->setTimestamp((int) $rp['expired_at']) : null,
            'provider_payload' => $rp,
        ]);

        return response()->json([
            'message' => 'Payment link created',
            'payment' => $payment,
            'payment_link' => $payment->short_url,
        ], 201);
    }

    public function show(int $id)
    {
        $payment = Payment::findOrFail($id);
        return response()->json($payment);
    }

    /**
     * Pull latest status from Razorpay and update this DB record.
     */
    public function sync(int $id)
    {
        $payment = Payment::findOrFail($id);

        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (empty($keyId) || empty($keySecret)) {
            return response()->json([
                'message' => 'Razorpay keys are missing. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in .env',
            ], 500);
        }

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->get('https://api.razorpay.com/v1/payment_links/' . $payment->razorpay_payment_link_id);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Failed to fetch payment from Razorpay',
                'razorpay_error' => $response->json(),
            ], $response->status());
        }

        $rp = $response->json();

        $payment->update([
            'reference_id' => $rp['reference_id'] ?? $payment->reference_id,
            'amount' => $rp['amount'] ?? $payment->amount,
            'amount_paid' => $rp['amount_paid'] ?? $payment->amount_paid,
            'amount_due' => $rp['amount_due'] ?? $payment->amount_due,
            'currency' => $rp['currency'] ?? $payment->currency,
            'description' => $rp['description'] ?? $payment->description,
            'customer_name' => $rp['customer']['name'] ?? $payment->customer_name,
            'customer_email' => $rp['customer']['email'] ?? $payment->customer_email,
            'customer_contact' => $rp['customer']['contact'] ?? $payment->customer_contact,
            'short_url' => $rp['short_url'] ?? $payment->short_url,
            'status' => $rp['status'] ?? $payment->status,
            'expires_at' => !empty($rp['expire_by']) ? now()->setTimestamp((int) $rp['expire_by']) : $payment->expires_at,
            'paid_at' => !empty($rp['paid_at']) ? now()->setTimestamp((int) $rp['paid_at']) : $payment->paid_at,
            'cancelled_at' => !empty($rp['cancelled_at']) ? now()->setTimestamp((int) $rp['cancelled_at']) : $payment->cancelled_at,
            'expired_at' => !empty($rp['expired_at']) ? now()->setTimestamp((int) $rp['expired_at']) : $payment->expired_at,
            'provider_payload' => $rp,
        ]);

        return response()->json([
            'message' => 'Payment synced',
            'payment' => $payment->fresh(),
        ]);
    }
}
