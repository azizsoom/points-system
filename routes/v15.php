<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function v15_is_admin(): bool
{
    $user = current_user_record();
    return $user && (($user->role ?? 'staff') === 'admin');
}

function v15_user_branch_filter($query, ?object $user = null)
{
    $user = $user ?: current_user_record();
    if ($user && (($user->role ?? 'staff') !== 'admin') && !empty($user->branch_id)) {
        $query->where('invoices.branch_id', $user->branch_id);
    }
    return $query;
}

function v15_status_label(?string $status): string
{
    return match ($status) {
        'new' => 'جديدة',
        'approved' => 'معتمدة',
        'rejected' => 'مرفوضة',
        'cancelled' => 'ملغاة',
        'paid' => 'مصروفة',
        default => $status ?: 'جديدة',
    };
}

function v15_status_badge(?string $status): string
{
    $class = match ($status) {
        'new' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-rose-100 text-rose-800',
        'cancelled' => 'bg-slate-100 text-slate-700',
        'paid' => 'bg-blue-100 text-blue-800',
        default => 'bg-slate-100 text-slate-700',
    };
    return '<span class="rounded-full px-3 py-1 text-xs '.$class.'">'.v15_status_label($status).'</span>';
}

function v15_can_approve(?object $user = null): bool
{
    $user = $user ?: current_user_record();
    return $user && (($user->role ?? 'staff') === 'admin' || ($user->can_approve_invoices ?? false));
}

function v15_eligible_amount(float $amount, float $discount, float $shipping): float
{
    return max(0, $amount - $discount - $shipping);
}

function v15_reward_for_invoice(float $eligible, float $rate): float
{
    return round($eligible * ($rate / 100), 2);
}

function v15_audit_change(string $action, string $section, $before = null, $after = null): void
{
    try {
        if (!Schema::hasTable('audit_logs')) return;
        $user = current_user_record();
        DB::table('audit_logs')->insert([
            'user_id' => $user->id ?? null,
            'actor_type' => $user ? (($user->role ?? 'staff') === 'admin' ? 'مدير' : 'موظف') : 'النظام',
            'actor_name' => $user->name ?? 'النظام',
            'action' => $action,
            'section' => $section,
            'method' => request()->method(),
            'path' => request()->path(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 900),
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {}
}

Route::get('/invoices', function (Request $request) {
    $user = require_user_record();
    $branches = DB::table('branches')->where('active', true)->get();
    $bopts = '<option value="">كل الفروع</option>';
    foreach ($branches as $b) $bopts .= '<option value="'.$b->id.'" '.($request->branch_id == $b->id ? 'selected' : '').'>'.e($b->name).'</option>';

    $base = DB::table('invoices')
        ->join('agents', 'agents.id', '=', 'invoices.agent_id')
        ->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
        ->leftJoin('users', 'users.id', '=', 'invoices.user_id')
        ->select('invoices.*', 'agents.name as agent_name', 'agents.referral_code', 'branches.name as branch_name', 'users.name as user_name');

    v15_user_branch_filter($base, $user);
    if ($request->filled('status')) $base->where('invoice_status', $request->status);
    if ($request->filled('branch_id') && v15_is_admin()) $base->where('invoices.branch_id', $request->branch_id);
    if ($request->filled('q')) {
        $q = '%'.$request->q.'%';
        $base->where(function ($w) use ($q) {
            $w->where('invoice_number', 'like', $q)->orWhere('agents.name', 'like', $q)->orWhere('agents.referral_code', 'like', $q);
        });
    }

    $invoices = $base->latest('invoices.id')->limit(150)->get();

    $html = '<div class="page-head"><div><h1>الفواتير والنقاط</h1><p class="text-slate-500">الفاتورة تبدأ جديدة ثم يعتمدها المدير قبل تثبيت النقاط</p></div></div>';
    $html .= '<div class="card p-5 mb-6"><form method="GET" action="/invoices" class="grid lg:grid-cols-4 gap-3"><input class="input" name="q" value="'.e($request->q).'" placeholder="بحث برقم الفاتورة أو المندوب"><select class="input" name="status"><option value="">كل الحالات</option><option value="new" '.($request->status==='new'?'selected':'').'>جديدة</option><option value="approved" '.($request->status==='approved'?'selected':'').'>معتمدة</option><option value="rejected" '.($request->status==='rejected'?'selected':'').'>مرفوضة</option><option value="cancelled" '.($request->status==='cancelled'?'selected':'').'>ملغاة</option><option value="paid" '.($request->status==='paid'?'selected':'').'>مصروفة</option></select><select class="input" name="branch_id">'.$bopts.'</select><button class="btn btn-primary">بحث</button></form></div>';

    $html .= '<div class="card p-5 mb-6"><h2 class="font-black text-xl mb-4">إضافة فاتورة جديدة</h2><form method="POST" action="/invoices" enctype="multipart/form-data" class="grid lg:grid-cols-8 gap-3"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="invoice_number" placeholder="رقم الفاتورة" required><input class="input" name="agent_query" placeholder="كود/جوال/اسم المندوب" required><input class="input" name="amount" type="number" step="0.01" placeholder="المبلغ قبل الضريبة" required><input class="input" name="tax_amount" type="number" step="0.01" value="0" placeholder="الضريبة"><input class="input" name="shipping_amount" type="number" step="0.01" value="0" placeholder="الشحن"><input class="input" name="discount" type="number" step="0.01" value="0" placeholder="الخصم"><select class="input" name="branch_id"><option value="">الفرع</option>'.$bopts.'</select><input class="input" name="attachment" type="file"><button class="btn btn-primary lg:col-span-8">إضافة كفاتورة جديدة بانتظار الاعتماد</button></form></div>';

    $html .= '<div class="card table-wrap"><table><thead><tr><th>التاريخ</th><th>الفاتورة</th><th>المندوب</th><th>الفرع</th><th>الموظف</th><th>المبلغ</th><th>ضريبة</th><th>شحن</th><th>خصم</th><th>المؤهل للنقاط</th><th>النسبة</th><th>النقاط</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>';
    foreach ($invoices as $i) {
        $html .= '<tr><td>'.riyadh_dt($i->created_at).'</td><td>'.e($i->invoice_number).'</td><td>'.e($i->agent_name).'</td><td>'.e($i->branch_name).'</td><td>'.e($i->user_name).'</td><td>'.money_fmt($i->amount).'</td><td>'.money_fmt($i->tax_amount ?? 0).'</td><td>'.money_fmt($i->shipping_amount ?? 0).'</td><td>'.money_fmt($i->discount).'</td><td>'.money_fmt($i->eligible_amount ?? $i->net_amount).'</td><td>'.$i->reward_rate.'%</td><td class="font-bold text-emerald-700">'.money_fmt($i->reward_amount).'</td><td>'.v15_status_badge($i->invoice_status ?? $i->status).'</td><td>';
        if (($i->invoice_status ?? 'new') === 'new' && v15_can_approve($user)) {
            $html .= '<form method="POST" action="/invoices/'.$i->id.'/approve" class="inline"><input type="hidden" name="_token" value="'.csrf_token().'"><button class="btn btn-primary">اعتماد</button></form>';
            $html .= '<form method="POST" action="/invoices/'.$i->id.'/reject" class="inline"><input type="hidden" name="_token" value="'.csrf_token().'"><button class="btn btn-soft">رفض</button></form>';
        }
        $html .= '<form method="POST" action="/invoices/'.$i->id.'/cancel" class="inline"><input type="hidden" name="_token" value="'.csrf_token().'"><button class="btn btn-soft">إلغاء</button></form></td></tr>';
    }
    $html .= '</tbody></table></div>';

    return admin_ui('الفواتير والنقاط', $html, $user);
});

Route::post('/invoices', function (Request $request) {
    $user = require_user_record();
    $agent = DB::table('agents')->where(function ($q) use ($request) {
        $q->where('referral_code', $request->agent_query)->orWhere('phone', $request->agent_query)->orWhere('name', 'like', '%'.$request->agent_query.'%');
    })->first();

    if (!$agent) return back()->with('error', 'لم يتم العثور على مندوب بهذا الكود/الجوال/الاسم.');
    if (($agent->active ?? true) == false || ($agent->status ?? 'active') !== 'active') return back()->with('error', 'هذا المندوب موقوف.');

    $request->validate(['invoice_number' => 'required']);
    if (DB::table('invoices')->where('invoice_number', $request->invoice_number)->exists()) {
        return back()->with('error', 'رقم الفاتورة مكرر ولا يمكن إدخاله مرة أخرى.');
    }

    $amount = (float) $request->amount;
    $tax = (float) ($request->tax_amount ?: 0);
    $shipping = (float) ($request->shipping_amount ?: 0);
    $discount = (float) ($request->discount ?: 0);
    $eligible = v15_eligible_amount($amount, $discount, $shipping);
    $rate = (float) $agent->commission_rate;
    $reward = v15_reward_for_invoice($eligible, $rate);

    $similar = DB::table('invoices')
        ->where('agent_id', $agent->id)
        ->whereDate('created_at', now()->toDateString())
        ->where('amount', $amount)
        ->exists();

    if ($similar && !$request->filled('confirm_similar')) {
        return back()->with('error', 'تنبيه: توجد فاتورة مشابهة لنفس المندوب ونفس المبلغ اليوم. إذا متأكد غيّر رقم الفاتورة أو راجع السجل قبل الإضافة.');
    }

    DB::table('invoices')->insert([
        'invoice_number' => $request->invoice_number,
        'agent_id' => $agent->id,
        'branch_id' => $request->branch_id ?: $user->branch_id,
        'user_id' => $user->id,
        'amount' => $amount,
        'tax_amount' => $tax,
        'shipping_amount' => $shipping,
        'discount' => $discount,
        'net_amount' => $eligible,
        'eligible_amount' => $eligible,
        'reward_rate' => $rate,
        'reward_amount' => $reward,
        'status' => 'active',
        'invoice_status' => 'new',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    v15_audit_change('إضافة فاتورة جديدة بانتظار الاعتماد', 'الفواتير والنقاط', null, ['invoice_number' => $request->invoice_number, 'reward' => $reward]);
    return back()->with('success', 'تمت إضافة الفاتورة كحالة جديدة وتحتاج اعتماد المدير.');
});

Route::post('/invoices/{id}/approve', function ($id) {
    $user = require_user_record();
    if (!v15_can_approve($user)) abort(403);
    $before = DB::table('invoices')->where('id', $id)->first();
    DB::table('invoices')->where('id', $id)->update(['invoice_status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'updated_at' => now()]);
    $after = DB::table('invoices')->where('id', $id)->first();
    v15_audit_change('اعتماد فاتورة وتثبيت نقاطها', 'الفواتير والنقاط', $before, $after);
    return back()->with('success', 'تم اعتماد الفاتورة.');
});

Route::post('/invoices/{id}/reject', function ($id) {
    $user = require_user_record();
    if (!v15_can_approve($user)) abort(403);
    $before = DB::table('invoices')->where('id', $id)->first();
    DB::table('invoices')->where('id', $id)->update(['invoice_status' => 'rejected', 'status' => 'cancelled', 'rejected_by' => $user->id, 'rejected_at' => now(), 'reject_reason' => 'رفض من المدير', 'updated_at' => now()]);
    $after = DB::table('invoices')->where('id', $id)->first();
    v15_audit_change('رفض فاتورة', 'الفواتير والنقاط', $before, $after);
    return back()->with('success', 'تم رفض الفاتورة.');
});

Route::post('/invoices/{id}/cancel', function ($id) {
    require_user_record();
    $before = DB::table('invoices')->where('id', $id)->first();
    DB::table('invoices')->where('id', $id)->update(['invoice_status' => 'cancelled', 'status' => 'cancelled', 'cancel_reason' => 'إلغاء من الإدارة', 'updated_at' => now()]);
    $after = DB::table('invoices')->where('id', $id)->first();
    v15_audit_change('إلغاء فاتورة', 'الفواتير والنقاط', $before, $after);
    return back()->with('success', 'تم إلغاء الفاتورة.');
});
