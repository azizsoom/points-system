<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/payouts', function () {
    $user = require_user_record();
    $agents = DB::table('agents')->orderBy('name')->get();
    $payouts = DB::table('payouts')
        ->join('agents', 'agents.id', '=', 'payouts.agent_id')
        ->leftJoin('users', 'users.id', '=', 'payouts.user_id')
        ->select('payouts.*', 'agents.name as agent_name', 'users.name as user_name')
        ->latest('payouts.id')
        ->limit(50)
        ->get();

    $totalAvailable = 0;
    $totalPayable = 0;
    foreach ($agents as $a) {
        $b = agent_balance($a->id);
        $totalAvailable += $b['available'];
        $totalPayable += $b['payable_now'];
    }

    $html = '<div class="page-head"><div><h1>الصرف والتصفير</h1><p class="text-slate-500">متابعة أرصدة المناديب وصرف المبالغ الصحيحة مع بقاء الكسور للشهر القادم</p></div></div>';

    $html .= '<div class="grid lg:grid-cols-3 md:grid-cols-2 gap-4 mb-6">'
        . '<div class="stat bg-gradient-to-br from-emerald-700 to-teal-400"><small>إجمالي الرصيد المتاح</small><b>'.money_fmt($totalAvailable).'</b></div>'
        . '<div class="stat bg-gradient-to-br from-blue-700 to-sky-400"><small>قابل للصرف الآن</small><b>'.money_fmt($totalPayable).'</b></div>'
        . '<div class="stat bg-gradient-to-br from-violet-700 to-fuchsia-400"><small>عدد المناديب</small><b>'.$agents->count().'</b></div>'
        . '</div>';

    $html .= '<div class="grid xl:grid-cols-2 gap-6 items-start">';

    $html .= '<section class="card table-wrap"><div class="p-5 border-b border-slate-200"><h2 class="font-black text-xl">أرصدة المناديب</h2><p class="text-slate-500 text-sm mt-1">كل صف يوضح الرصيد، المبلغ الصحيح القابل للصرف، والكسر المتبقي</p></div><table><thead><tr><th>المندوب</th><th>المتاح</th><th>يصرف الآن</th><th>الكسر الباقي</th><th>الإجراء</th></tr></thead><tbody>';
    foreach ($agents as $a) {
        $b = agent_balance($a->id);
        $html .= '<tr>'
            . '<td class="font-bold">'.e($a->name).'</td>'
            . '<td>'.money_fmt($b['available']).'</td>'
            . '<td><span class="font-bold text-emerald-700">'.money_fmt($b['payable_now']).'</span></td>'
            . '<td>'.money_fmt($b['fraction']).'</td>'
            . '<td><form method="POST" action="/payouts"><input type="hidden" name="_token" value="'.csrf_token().'"><input type="hidden" name="agent_id" value="'.$a->id.'"><button class="btn btn-primary" '.($b['payable_now'] <= 0 ? 'disabled style="opacity:.45;cursor:not-allowed"' : '').'>صرف المتاح</button></form></td>'
            . '</tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section class="card table-wrap"><div class="p-5 border-b border-slate-200"><h2 class="font-black text-xl">آخر عمليات الصرف</h2><p class="text-slate-500 text-sm mt-1">الوقت معروض بتوقيت الرياض</p></div><table><thead><tr><th>التاريخ والوقت</th><th>المندوب</th><th>المبلغ</th><th>الموظف</th></tr></thead><tbody>';
    foreach ($payouts as $p) {
        $html .= '<tr>'
            . '<td>'.riyadh_dt($p->created_at).'</td>'
            . '<td class="font-bold">'.e($p->agent_name).'</td>'
            . '<td><span class="font-bold text-blue-700">'.money_fmt($p->amount).'</span></td>'
            . '<td>'.e($p->user_name).'</td>'
            . '</tr>';
    }
    if ($payouts->count() === 0) {
        $html .= '<tr><td colspan="4" class="text-center text-slate-500 py-8">لا توجد عمليات صرف حتى الآن</td></tr>';
    }
    $html .= '</tbody></table></section></div>';

    return admin_ui('الصرف والتصفير', $html, $user);
});

Route::post('/payouts', function (Request $request) {
    $user = require_user_record();
    $agent = DB::table('agents')->find($request->agent_id);
    abort_unless($agent, 404);

    $balance = agent_balance($agent->id);
    $amount = floor($balance['available']);

    if ($amount <= 0) {
        return back()->with('error', 'لا يوجد مبلغ صحيح قابل للصرف. الكسور تبقى للشهر القادم.');
    }

    DB::table('payouts')->insert([
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'amount' => $amount,
        'method' => 'صرف إداري',
        'note' => 'صرف الرقم الصحيح وترك الكسور',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return back()->with('success', 'تم صرف '.money_fmt($amount).' وبقي الكسر '.money_fmt($balance['available'] - $amount));
});
