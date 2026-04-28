<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function audit_section_label(string $path): string
{
    return match (true) {
        Str::startsWith($path, 'dashboard') => 'الرئيسية',
        Str::startsWith($path, 'invoices') => 'الفواتير والنقاط',
        Str::startsWith($path, 'agents') => 'المناديب',
        Str::startsWith($path, 'payouts') => 'الصرف والتصفير',
        Str::startsWith($path, 'branches') => 'الفروع',
        Str::startsWith($path, 'employees') => 'الموظفون',
        Str::startsWith($path, 'agent/dashboard') => 'بوابة المندوب',
        Str::startsWith($path, 'agent/login') => 'دخول المندوب',
        Str::startsWith($path, 'login') => 'دخول الموظفين',
        default => 'عام',
    };
}

function audit_action_label(Request $request, string $path): string
{
    $method = $request->method();

    if ($method === 'GET') {
        return match (true) {
            Str::startsWith($path, 'audit-logs') => 'فتح سجل العمليات',
            Str::startsWith($path, 'dashboard') => 'فتح لوحة المدير',
            Str::startsWith($path, 'invoices') => 'فتح صفحة الفواتير والنقاط',
            Str::startsWith($path, 'agents') => 'فتح صفحة المناديب',
            Str::startsWith($path, 'payouts') => 'فتح صفحة الصرف والتصفير',
            Str::startsWith($path, 'branches') => 'فتح صفحة الفروع',
            Str::startsWith($path, 'employees') => 'فتح صفحة الموظفين',
            Str::startsWith($path, 'agent/dashboard') => 'مندوب فتح صفحة رصيده',
            default => 'فتح صفحة',
        };
    }

    return match (true) {
        $path === 'login' => 'تسجيل دخول موظف',
        $path === 'agent/login' => 'تسجيل دخول مندوب',
        Str::startsWith($path, 'invoices') => 'إضافة أو تعديل فاتورة/نقاط',
        Str::startsWith($path, 'agents') => 'إضافة أو تعديل مندوب',
        Str::startsWith($path, 'payouts') => 'تنفيذ عملية صرف',
        Str::startsWith($path, 'branches') => 'إضافة أو تعديل فرع',
        Str::startsWith($path, 'employees') => 'إضافة أو تعديل موظف',
        $path === 'logout' => 'تسجيل خروج موظف',
        default => 'تنفيذ عملية',
    };
}

function audit_record(Request $request): void
{
    try {
        if (!Schema::hasTable('audit_logs')) return;

        $path = trim($request->path(), '/');
        if ($path === '') $path = 'dashboard';
        if (Str::startsWith($path, 'attachments') || Str::startsWith($path, 'up')) return;

        $userId = session('user_id');
        $agentId = session('agent_id');
        $user = $userId ? DB::table('users')->where('id', $userId)->first() : null;
        $agent = $agentId ? DB::table('agents')->where('id', $agentId)->first() : null;

        if (!$user && !$agent && !in_array($path, ['login', 'agent/login'], true)) return;

        $actorType = $user ? (($user->role ?? 'staff') === 'admin' ? 'مدير' : 'موظف') : ($agent ? 'مندوب' : 'زائر');
        $actorName = $user->name ?? $agent->name ?? 'غير معروف';

        $details = null;
        if ($request->isMethod('POST')) {
            $safe = collect($request->except(['password', '_token', 'attachment']))
                ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))
                ->toArray();
            $details = $safe ? json_encode($safe, JSON_UNESCAPED_UNICODE) : null;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'actor_type' => $actorType,
            'actor_name' => $actorName,
            'action' => audit_action_label($request, $path),
            'section' => audit_section_label($path),
            'method' => $request->method(),
            'path' => $path,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 900),
            'details' => $details,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        // لا نوقف النظام بسبب السجل
    }
}

app()->terminating(function () {
    audit_record(request());
});

Route::get('/audit-logs', function (Request $request) {
    $user = require_admin_record();

    $query = DB::table('audit_logs')->latest('id');
    if ($request->filled('actor_type')) $query->where('actor_type', $request->actor_type);
    if ($request->filled('section')) $query->where('section', $request->section);
    if ($request->filled('q')) {
        $q = '%'.$request->q.'%';
        $query->where(function ($w) use ($q) {
            $w->where('actor_name', 'like', $q)->orWhere('action', 'like', $q)->orWhere('path', 'like', $q)->orWhere('details', 'like', $q);
        });
    }

    $logs = $query->limit(300)->get();

    $html = '<div class="page-head"><div><h1>سجل العمليات</h1><p class="text-slate-500">متابعة دخول الموظفين والمناديب وكل حركة داخل النظام بتوقيت الرياض</p></div></div>';
    $html .= '<div class="card p-5 mb-6"><form method="GET" action="/audit-logs" class="grid lg:grid-cols-4 gap-3"><input class="input" name="q" value="'.e($request->q).'" placeholder="بحث بالاسم أو العملية"><select class="input" name="actor_type"><option value="">كل المستخدمين</option><option '.($request->actor_type==='مدير'?'selected':'').' value="مدير">مدير</option><option '.($request->actor_type==='موظف'?'selected':'').' value="موظف">موظف</option><option '.($request->actor_type==='مندوب'?'selected':'').' value="مندوب">مندوب</option></select><select class="input" name="section"><option value="">كل الأقسام</option><option value="الفواتير والنقاط">الفواتير والنقاط</option><option value="المناديب">المناديب</option><option value="الصرف والتصفير">الصرف والتصفير</option><option value="بوابة المندوب">بوابة المندوب</option><option value="الموظفون">الموظفون</option></select><button class="btn btn-primary">بحث</button></form></div>';
    $html .= '<div class="card table-wrap"><table><thead><tr><th>التاريخ والوقت</th><th>النوع</th><th>الاسم</th><th>العملية</th><th>القسم</th><th>المسار</th><th>IP</th><th>تفاصيل</th></tr></thead><tbody>';

    foreach ($logs as $log) {
        $details = $log->details ? e(Str::limit($log->details, 120)) : '-';
        $badge = match ($log->actor_type) {
            'مدير' => 'bg-violet-100 text-violet-800',
            'موظف' => 'bg-blue-100 text-blue-800',
            'مندوب' => 'bg-emerald-100 text-emerald-800',
            default => 'bg-slate-100 text-slate-700',
        };
        $html .= '<tr><td>'.riyadh_dt($log->created_at).'</td><td><span class="rounded-full px-3 py-1 text-xs '.$badge.'">'.e($log->actor_type).'</span></td><td class="font-bold">'.e($log->actor_name).'</td><td>'.e($log->action).'</td><td>'.e($log->section).'</td><td>'.e($log->path).'</td><td>'.e($log->ip_address).'</td><td>'.$details.'</td></tr>';
    }

    if ($logs->count() === 0) {
        $html .= '<tr><td colspan="8" class="text-center text-slate-500 py-8">لا توجد عمليات مسجلة حتى الآن</td></tr>';
    }

    $html .= '</tbody></table></div>';
    return admin_ui('سجل العمليات', $html, $user);
});
