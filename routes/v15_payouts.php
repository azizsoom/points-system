<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function v15_agent_balance(int $agentId): array
{
    $earned = (float) DB::table('invoices')
        ->where('agent_id', $agentId)
        ->whereIn('invoice_status', ['approved', 'paid'])
        ->where('status', 'active')
        ->sum('reward_amount');

    $paid = (float) DB::table('payouts')->where('agent_id', $agentId)->sum('amount');
    $available = max(0, $earned - $paid);

    return [
        'earned' => $earned,
        'paid' => $paid,
        'available' => $available,
        'payable_now' => floor($available),
        'fraction' => round($available - floor($available), 2),
    ];
}

Route::get('/payouts', function () {
    $user = require_user_record();
    $agents = DB::table('agents')->orderBy('name')->get();
    $payouts = DB::table('payouts')
        ->join('agents', 'agents.id', '=', 'payouts.agent_id')
        ->leftJoin('users', 'users.id', '=', 'payouts.user_id')
        ->select('payouts.*', 'agents.name as agent_name', 'users.name as user_name')
        ->latest('payouts.id')->limit(80)->get();

    $totalAvailable = 0;
    $totalPayable = 0;
    foreach ($agents as $a) {
        $b = v15_agent_balance($a->id);
        $totalAvailable += $b['available'];
        $totalPayable += $b['payable_now'];
    }

    $html = '<div class="page-head"><div><h1>الصرف والتصفير</h1><p class="text-slate-500">المستحقات هنا من الفواتير المعتمدة فقط. الفاتورة الجديدة لا تدخل بالصرف إلا بعد الاعتماد.</p></div></div>';
    $html .= '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-4 mb-6"><div class="stat bg-gradient-to-br from-emerald-700 to-teal-400"><small>رصيد معتمد متاح</small><b>'.money_fmt($totalAvailable).'</b></div><div class="stat bg-gradient-to-br from-blue-700 to-sky-400"><small>قابل للصرف الآن</small><b>'.money_fmt($totalPayable).'</b></div><div class="stat bg-gradient-to-br from-violet-700 to-fuchsia-400"><small>عدد المناديب</small><b>'.$agents->count().'</b></div></div>';

    $html .= '<div class="grid xl:grid-cols-2 gap-6 items-start"><section class="card table-wrap"><div class="p-5 border-b border-slate-200"><h2 class="font-black text-xl">أرصدة المناديب المعتمدة</h2></div><table><thead><tr><th>المندوب</th><th>إجمالي المعتمد</th><th>المصروف</th><th>المتاح</th><th>يصرف الآن</th><th>الكسر</th><th>الإجراء</th></tr></thead><tbody>';
    foreach ($agents as $a) {
        $b = v15_agent_balance($a->id);
        $html .= '<tr><td class="font-bold">'.e($a->name).'</td><td>'.money_fmt($b['earned']).'</td><td>'.money_fmt($b['paid']).'</td><td>'.money_fmt($b['available']).'</td><td><span class="font-bold text-emerald-700">'.money_fmt($b['payable_now']).'</span></td><td>'.money_fmt($b['fraction']).'</td><td><form method="POST" action="/payouts"><input type="hidden" name="_token" value="'.csrf_token().'"><input type="hidden" name="agent_id" value="'.$a->id.'"><button class="btn btn-primary" '.($b['payable_now'] <= 0 ? 'disabled style="opacity:.45;cursor:not-allowed"' : '').'>صرف المتاح</button></form></td></tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section class="card table-wrap"><div class="p-5 border-b border-slate-200"><h2 class="font-black text-xl">آخر عمليات الصرف</h2></div><table><thead><tr><th>التاريخ والوقت</th><th>المندوب</th><th>المبلغ</th><th>الموظف</th><th>إيصال</th></tr></thead><tbody>';
    foreach ($payouts as $p) {
        $html .= '<tr><td>'.riyadh_dt($p->created_at).'</td><td class="font-bold">'.e($p->agent_name).'</td><td><span class="font-bold text-blue-700">'.money_fmt($p->amount).'</span></td><td>'.e($p->user_name).'</td><td><a class="btn btn-soft" target="_blank" href="/payouts/'.$p->id.'/receipt">إيصال</a></td></tr>';
    }
    if ($payouts->count() === 0) $html .= '<tr><td colspan="5" class="text-center text-slate-500 py-8">لا توجد عمليات صرف حتى الآن</td></tr>';
    $html .= '</tbody></table></section></div>';

    return admin_ui('الصرف والتصفير', $html, $user);
});

Route::post('/payouts', function (Request $request) {
    $user = require_user_record();
    $agent = DB::table('agents')->find($request->agent_id);
    abort_unless($agent, 404);

    $balance = v15_agent_balance($agent->id);
    $amount = floor($balance['available']);
    if ($amount <= 0) return back()->with('error', 'لا يوجد مبلغ صحيح قابل للصرف. يجب اعتماد الفواتير أولاً.');

    $payoutId = DB::table('payouts')->insertGetId([
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'amount' => $amount,
        'method' => 'صرف إداري',
        'note' => 'صرف الرقم الصحيح من الفواتير المعتمدة وترك الكسور',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('invoices')
        ->where('agent_id', $agent->id)
        ->where('invoice_status', 'approved')
        ->where('status', 'active')
        ->update(['invoice_status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);

    v15_audit_change('صرف مستحقات مندوب', 'الصرف والتصفير', null, ['agent_id' => $agent->id, 'amount' => $amount, 'payout_id' => $payoutId]);
    return back()->with('success', 'تم صرف '.money_fmt($amount).' وبقي الكسر '.money_fmt($balance['available'] - $amount));
});

Route::get('/payouts/{id}/receipt', function ($id) {
    require_user_record();
    $payout = DB::table('payouts')->where('id', $id)->first();
    abort_unless($payout, 404);
    $agent = DB::table('agents')->find($payout->agent_id);
    $user = $payout->user_id ? DB::table('users')->find($payout->user_id) : null;
    return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>إيصال صرف</title><style>body{font-family:Tahoma,Arial;background:#f8fafc;padding:30px}.paper{max-width:760px;margin:auto;background:white;border:1px solid #e2e8f0;border-radius:24px;padding:32px}.row{display:flex;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding:14px 0}.title{text-align:center;font-size:28px;font-weight:900;margin-bottom:24px}.stamp{margin-top:35px;border:2px dashed #94a3b8;border-radius:20px;padding:20px;text-align:center}@media print{button{display:none}body{background:white}.paper{border:0}}</style></head><body><div class="paper"><div class="title">إيصال صرف مستحقات مندوب</div><div class="row"><b>رقم الإيصال</b><span>#'.$payout->id.'</span></div><div class="row"><b>اسم المندوب</b><span>'.e($agent->name ?? '-').'</span></div><div class="row"><b>المبلغ المصروف</b><span>'.money_fmt($payout->amount).'</span></div><div class="row"><b>الموظف</b><span>'.e($user->name ?? '-').'</span></div><div class="row"><b>التاريخ</b><span>'.riyadh_dt($payout->created_at).'</span></div><div class="stamp">توقيع المستلم / الختم</div><button onclick="window.print()" style="margin-top:25px;width:100%;padding:14px;border:0;border-radius:14px;background:#0f172a;color:white;font-weight:800">طباعة أو حفظ PDF</button></div></body></html>';
});

Route::get('/agent/dashboard', function () {
    $id = session('agent_id');
    if (!$id) return redirect('/agent/login');

    $agent = DB::table('agents')->find($id);
    abort_unless($agent, 404);
    $balance = v15_agent_balance($id);
    $invoices = DB::table('invoices')->where('agent_id', $id)->whereIn('invoice_status', ['approved', 'paid'])->latest('id')->limit(100)->get();

    $html = '<div class="page-head"><div><h1>مرحباً '.e($agent->name).'</h1><p class="text-slate-500">تظهر لك الفواتير المعتمدة فقط</p></div><a class="btn btn-soft" href="/agent/logout">خروج</a></div>';
    $html .= '<div class="grid lg:grid-cols-4 md:grid-cols-2 gap-4 mb-6"><div class="stat bg-gradient-to-br from-emerald-700 to-teal-400"><small>الرصيد المتاح</small><b>'.money_fmt($balance['available']).'</b></div><div class="stat bg-gradient-to-br from-blue-700 to-sky-400"><small>المصروف</small><b>'.money_fmt($balance['paid']).'</b></div><div class="stat bg-gradient-to-br from-amber-600 to-orange-500"><small>الكسر المتبقي</small><b>'.money_fmt($balance['fraction']).'</b></div><div class="stat bg-gradient-to-br from-violet-700 to-fuchsia-400"><small>نسبتك</small><b>'.$agent->commission_rate.'%</b></div></div>';
    $html .= '<div class="card table-wrap"><div class="p-5"><h2 class="font-black text-xl">كشف الفواتير المعتمدة</h2></div><table><tr><th>التاريخ</th><th>الفاتورة</th><th>المبلغ المؤهل</th><th>النسبة</th><th>المكافأة</th><th>الحالة</th></tr>';
    foreach ($invoices as $i) $html .= '<tr><td>'.riyadh_dt($i->created_at).'</td><td>'.e($i->invoice_number).'</td><td>'.money_fmt($i->eligible_amount ?? $i->net_amount).'</td><td>'.$i->reward_rate.'%</td><td class="font-bold text-emerald-700">'.money_fmt($i->reward_amount).'</td><td>'.v15_status_badge($i->invoice_status).'</td></tr>';
    if ($invoices->count() === 0) $html .= '<tr><td colspan="6" class="text-center text-slate-500 py-8">لا توجد فواتير معتمدة حتى الآن</td></tr>';
    $html .= '</table></div>';

    return admin_ui('بوابة المندوب', $html);
});
