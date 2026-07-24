<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\FeePayment;
use App\Models\FinanceTransaction;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\SchoolProject;
use App\Models\ProjectTransaction;
use App\Models\ExpenseRecord;
use App\Models\IncomeRecord;
use App\Models\SalaryStructure;
use App\Models\Payslip;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\User;

class FinanceController extends Controller
{
    private function schoolId()
    {
        return auth()->user()->school_id;
    }

    private function activeSession()
    {
        return get_school_settings($this->schoolId())->value('running_session');
    }

    // H10: only accept an account id that belongs to the caller's school (else null) — blocks cross-school tagging.
    private function ownAccountId($id)
    {
        if (!$id) return null;
        return Account::where('id', $id)->where('school_id', $this->schoolId())->exists() ? $id : null;
    }

    private function nextNo($prefix, $model)
    {
        $count = $model::where('school_id', $this->schoolId())->count();
        return $prefix . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }

    private function recomputeInvoice(Invoice $inv)
    {
        $net = (float)$inv->total_amount + (float)$inv->fine - (float)$inv->discount;
        $paid = (float)FeePayment::where('invoice_id', $inv->id)->sum('amount');
        $balance = round($net - $paid, 2);
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $inv->update(['paid_amount' => $paid, 'balance' => max(0, $balance), 'status' => $status]);
    }

    /* ============================================================ FEE HEADS */

    public function feeHeads()
    {
        $fee_heads = FeeHead::where('school_id', $this->schoolId())->orderBy('name')->get();
        return view('admin.finance.fee_heads', compact('fee_heads'));
    }

    public function feeHeadStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        FeeHead::create([
            'school_id' => $this->schoolId(),
            'name' => $request->name,
            'description' => $request->description,
        ]);
        return redirect()->back()->with('message', get_phrase('Fee head added.'));
    }

    public function feeHeadDelete($id)
    {
        FeeHead::where('id', $id)->where('school_id', $this->schoolId())->delete();
        return redirect()->back()->with('message', get_phrase('Fee head deleted.'));
    }

    /* ============================================================ FEE STRUCTURES */

    public function structures()
    {
        $structures = FeeStructure::where('school_id', $this->schoolId())->orderByDesc('id')->get();
        return view('admin.finance.structures', compact('structures'));
    }

    public function structureCreate()
    {
        $classes = Classes::where('school_id', $this->schoolId())->get();
        $fee_heads = FeeHead::where('school_id', $this->schoolId())->orderBy('name')->get();
        return view('admin.finance.structure_create', compact('classes', 'fee_heads'));
    }

    public function structureStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'class_id' => 'required',
        ]);

        $structure = FeeStructure::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'class_id' => $request->class_id,
            'title' => $request->title,
            'due_date' => $request->due_date ? strtotime($request->due_date) : null,
        ]);

        foreach (($request->amount ?? []) as $headId => $amount) {
            if ($amount === null || $amount === '' || (float)$amount <= 0) continue;
            FeeStructureItem::create([
                'structure_id' => $structure->id,
                'fee_head_id' => $headId,
                'amount' => (float)$amount,
            ]);
        }

        return redirect()->route('admin.finance.structure.show', $structure->id)
            ->with('message', get_phrase('Fee structure created. You can now generate invoices.'));
    }

    public function structureShow($id)
    {
        $structure = FeeStructure::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $structure->load('items.head');
        $class = Classes::find($structure->class_id);
        $studentCount = Enrollment::where('class_id', $structure->class_id)
            ->where('school_id', $this->schoolId())->count();
        $invoiced = Invoice::where('structure_id', $id)->count();
        return view('admin.finance.structure_show', compact('structure', 'class', 'studentCount', 'invoiced'));
    }

    public function structureDelete($id)
    {
        $structure = FeeStructure::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        FeeStructureItem::where('structure_id', $id)->delete();
        $structure->delete();
        return redirect()->route('admin.finance.structures')->with('message', get_phrase('Fee structure deleted.'));
    }

    /* generate an invoice per enrolled student in the structure's class */
    public function generateInvoices($id)
    {
        $structure = FeeStructure::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $structure->load('items.head');
        $total = $structure->items->sum('amount');

        $enrollments = Enrollment::where('class_id', $structure->class_id)
            ->where('school_id', $this->schoolId())->get();

        $made = 0;
        foreach ($enrollments as $en) {
            if (Invoice::where('structure_id', $id)->where('student_id', $en->user_id)->exists()) continue;

            $invoice = Invoice::create([
                'school_id' => $this->schoolId(),
                'session_id' => $this->activeSession(),
                'student_id' => $en->user_id,
                'class_id' => $structure->class_id,
                'section_id' => $en->section_id,
                'structure_id' => $structure->id,
                'invoice_no' => $this->nextNo('INV', Invoice::class),
                'title' => $structure->title,
                'total_amount' => $total,
                'balance' => $total,
                'status' => 'unpaid',
                'due_date' => $structure->due_date,
            ]);
            foreach ($structure->items as $it) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'fee_head_id' => $it->fee_head_id,
                    'title' => optional($it->head)->name ?? 'Fee',
                    'amount' => $it->amount,
                ]);
            }
            $made++;
        }

        return redirect()->route('admin.finance.invoices', ['class_id' => $structure->class_id])
            ->with('message', $made . ' ' . get_phrase('invoices generated.'));
    }

    /* ============================================================ INVOICES */

    public function invoices(Request $request)
    {
        $class_id = $request->class_id ?? '';
        $status = $request->status ?? '';
        $search = $request->search ?? '';

        $invoices = Invoice::where('invoices.school_id', $this->schoolId())
            ->when($class_id !== '', fn($q) => $q->where('class_id', $class_id))
            ->when($status !== '', fn($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $ids = User::where('name', 'LIKE', "%{$search}%")->pluck('id');
                $q->where(fn($qq) => $qq->where('invoice_no', 'LIKE', "%{$search}%")->orWhereIn('student_id', $ids));
            })
            ->orderByDesc('id')->paginate(15)->appends($request->query());

        $classes = Classes::where('school_id', $this->schoolId())->get();

        // summary — H11: "billed" = net owed (total + fine − discount) so Billed − Collected == Outstanding always.
        $base = Invoice::where('school_id', $this->schoolId());
        $collected = (float)(clone $base)->sum('paid_amount');
        $outstanding = (float)(clone $base)->sum('balance');
        $summary = [
            'billed' => round($collected + $outstanding, 2),
            'collected' => $collected,
            'outstanding' => $outstanding,
        ];

        return view('admin.finance.invoices', compact('invoices', 'classes', 'class_id', 'status', 'search', 'summary'));
    }

    public function invoiceShow($id)
    {
        $invoice = Invoice::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $invoice->load('items', 'payments');
        $student = User::find($invoice->student_id);
        $class = Classes::find($invoice->class_id);
        $section = Section::find($invoice->section_id);
        // Recording a *received* payment is a manual/offline entry — only the
        // two channels this college actually uses.
        $methods = ['mpesa' => 'M-Pesa', 'bank' => 'Bank transfer'];
        $accounts = Account::where('school_id', $this->schoolId())->orderBy('name')->get();
        return view('admin.finance.invoice_show', compact('invoice', 'student', 'class', 'section', 'methods', 'accounts'));
    }

    public function recordPayment(Request $request, $id)
    {
        $invoice = Invoice::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $request->validate(['amount' => 'required|numeric|min:0.01']);

        // apply optional discount/fine adjustments
        if ($request->filled('discount')) $invoice->discount = (float)$request->discount;
        if ($request->filled('fine')) $invoice->fine = (float)$request->fine;
        $invoice->save();

        // M1: reject overpayment server-side (the form's max= is client-only).
        $alreadyPaid = (float) FeePayment::where('invoice_id', $invoice->id)->sum('amount');
        $owed = round((float)$invoice->total_amount + (float)$invoice->fine - (float)$invoice->discount - $alreadyPaid, 2);
        if ((float)$request->amount > $owed + 0.01) {
            return redirect()->back()->with('error', get_phrase('Payment exceeds the outstanding balance of') . ' ' . number_format($owed, 2));
        }

        $payment = FeePayment::create([
            'school_id' => $this->schoolId(),
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'amount' => (float)$request->amount,
            'method' => $request->method ?: 'cash',
            'reference' => $request->reference,
            'receipt_no' => $this->nextNo('RCP', FeePayment::class),
            'paid_on' => $request->paid_on ? strtotime($request->paid_on) : time(),
            'recorded_by' => auth()->user()->id,
            'note' => $request->note,
        ]);

        $this->recomputeInvoice($invoice);

        // post to single-entry ledger
        FinanceTransaction::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'type' => 'income',
            'category' => 'Student fees',
            'amount' => (float)$request->amount,
            'description' => 'Fee payment · ' . $invoice->invoice_no . ' · receipt ' . $payment->receipt_no,
            'txn_date' => $payment->paid_on,
            'source_type' => 'fee_payment',
            'source_id' => $payment->id,
            'account_id' => $this->ownAccountId($request->account_id),
        ]);

        // Koha two-way settle: if this invoice mirrors a Koha library fine, push the
        // payment back so the borrower's Koha account settles too.
        $fine = \DB::table('koha_fine_sync')->where('invoice_id', $invoice->id)->first();
        if ($fine) {
            $map = \DB::table('koha_borrower_map')->where('user_id', $invoice->student_id)->first();
            if ($map && $map->koha_borrowernumber) {
                try {
                    (new \App\Services\Koha())->creditPatron(
                        $map->koha_borrowernumber,
                        (float) $request->amount,
                        'Library fine paid via school portal · receipt ' . $payment->receipt_no
                    );
                } catch (\Throwable $e) { /* never block the in-app receipt on a Koha hiccup */ }
            }
        }

        return redirect()->route('admin.finance.receipt', $payment->id)
            ->with('message', get_phrase('Payment recorded.'));
    }

    public function receipt($payment_id)
    {
        $payment = FeePayment::where('id', $payment_id)->where('school_id', $this->schoolId())->firstOrFail();
        $invoice = Invoice::find($payment->invoice_id);
        $student = User::find($payment->student_id);
        $school = \DB::table('schools')->where('id', $this->schoolId())->first();
        return view('admin.finance.receipt', compact('payment', 'invoice', 'student', 'school'));
    }

    public function invoiceDelete($id)
    {
        $invoice = Invoice::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        InvoiceItem::where('invoice_id', $id)->delete();
        FeePayment::where('invoice_id', $id)->delete();
        FinanceTransaction::where('source_type', 'fee_payment')
            ->whereIn('source_id', FeePayment::where('invoice_id', $id)->pluck('id'))->delete();
        $invoice->delete();
        return redirect()->back()->with('message', get_phrase('Invoice deleted.'));
    }

    public function studentStatement($student_id)
    {
        $student = User::where('id', $student_id)->where('school_id', $this->schoolId())->firstOrFail();
        $invoices = Invoice::where('student_id', $student_id)->where('school_id', $this->schoolId())
            ->orderByDesc('id')->get();
        $payments = FeePayment::where('student_id', $student_id)->where('school_id', $this->schoolId())
            ->orderByDesc('paid_on')->get();
        $totals = [
            'billed' => (float)$invoices->sum('total_amount'),
            'paid' => (float)$invoices->sum('paid_amount'),
            'balance' => (float)$invoices->sum('balance'),
        ];
        $class = Classes::find(optional($invoices->first())->class_id);
        return view('admin.finance.statement', compact('student', 'invoices', 'payments', 'totals', 'class'));
    }

    /* ============================================================ STUDENT / PARENT */

    public function studentInvoices()
    {
        $invoices = Invoice::where('student_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->orderByDesc('id')->get();
        $totals = [
            'billed' => (float)$invoices->sum('total_amount'),
            'paid' => (float)$invoices->sum('paid_amount'),
            'balance' => (float)$invoices->sum('balance'),
        ];
        return view('student.finance.invoices', compact('invoices', 'totals'));
    }

    public function studentInvoiceShow($id)
    {
        $invoice = Invoice::where('id', $id)->where('student_id', auth()->user()->id)->firstOrFail();
        $invoice->load('items', 'payments');
        return view('student.finance.invoice_show', compact('invoice'));
    }

    public function parentInvoices()
    {
        $childIds = User::where('parent_id', auth()->user()->id)->pluck('id');
        $invoices = Invoice::whereIn('student_id', $childIds)
            ->where('school_id', $this->schoolId())->orderByDesc('id')->get();
        $totals = [
            'billed' => (float)$invoices->sum('total_amount'),
            'paid' => (float)$invoices->sum('paid_amount'),
            'balance' => (float)$invoices->sum('balance'),
        ];
        return view('parent.finance.invoices', compact('invoices', 'totals'));
    }

    /* ============================================================ BUDGETS */

    public function budgets()
    {
        $budgets = Budget::where('school_id', $this->schoolId())->orderByDesc('id')->get();
        return view('admin.finance.budgets', compact('budgets'));
    }

    public function budgetCreate()
    {
        // suggest categories from existing ledger activity
        $categories = FinanceTransaction::where('school_id', $this->schoolId())
            ->distinct()->pluck('category')->filter()->values();
        return view('admin.finance.budget_create', compact('categories'));
    }

    public function budgetStore(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $budget = Budget::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'title' => $request->title,
            'note' => $request->note,
        ]);
        $types = $request->type ?? [];
        $cats = $request->category ?? [];
        $amts = $request->planned ?? [];
        foreach ($cats as $i => $cat) {
            if (trim((string)$cat) === '' || (float)($amts[$i] ?? 0) <= 0) continue;
            BudgetItem::create([
                'budget_id' => $budget->id,
                'type' => ($types[$i] ?? 'expense') === 'income' ? 'income' : 'expense',
                'category' => $cat,
                'planned_amount' => (float)$amts[$i],
            ]);
        }
        return redirect()->route('admin.finance.budget.show', $budget->id)->with('message', get_phrase('Budget created.'));
    }

    public function budgetShow($id)
    {
        $budget = Budget::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $budget->load('items');
        // actual per item = ledger sum by type + category for this session
        $rows = [];
        foreach ($budget->items as $it) {
            $actual = (float)FinanceTransaction::where('school_id', $this->schoolId())
                ->where('session_id', $budget->session_id)
                ->where('type', $it->type)
                ->where('category', $it->category)->sum('amount');
            $rows[] = ['item' => $it, 'actual' => $actual, 'variance' => (float)$it->planned_amount - $actual];
        }
        return view('admin.finance.budget_show', compact('budget', 'rows'));
    }

    public function budgetDelete($id)
    {
        $budget = Budget::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        BudgetItem::where('budget_id', $id)->delete();
        $budget->delete();
        return redirect()->route('admin.finance.budgets')->with('message', get_phrase('Budget deleted.'));
    }

    /* ============================================================ SCHOOL PROJECTS */

    public function projects()
    {
        $projects = SchoolProject::where('school_id', $this->schoolId())->orderByDesc('id')->get();
        return view('admin.finance.projects', compact('projects'));
    }

    public function projectStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'budget_amount' => 'required|numeric|min:0']);
        $project = SchoolProject::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'name' => $request->name,
            'description' => $request->description,
            'budget_amount' => (float)$request->budget_amount,
            'start_date' => $request->start_date ? strtotime($request->start_date) : null,
            'end_date' => $request->end_date ? strtotime($request->end_date) : null,
            'status' => $request->status ?: 'ongoing',
        ]);
        return redirect()->route('admin.finance.project.show', $project->id)->with('message', get_phrase('Project created.'));
    }

    public function projectShow($id)
    {
        $project = SchoolProject::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $txns = ProjectTransaction::where('project_id', $id)->orderByDesc('txn_date')->get();
        $funded = (float)$txns->where('type', 'funding')->sum('amount');
        $spent = (float)$txns->where('type', 'expense')->sum('amount');
        return view('admin.finance.project_show', compact('project', 'txns', 'funded', 'spent'));
    }

    public function projectTxnStore(Request $request, $id)
    {
        $project = SchoolProject::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $request->validate(['type' => 'required|in:funding,expense', 'amount' => 'required|numeric|min:0.01']);

        $txn = ProjectTransaction::create([
            'school_id' => $this->schoolId(),
            'project_id' => $project->id,
            'type' => $request->type,
            'amount' => (float)$request->amount,
            'description' => $request->description,
            'txn_date' => $request->txn_date ? strtotime($request->txn_date) : time(),
        ]);

        // post to the main ledger so P&L / cash flow includes project activity
        FinanceTransaction::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'type' => $request->type === 'funding' ? 'income' : 'expense',
            'category' => 'Project: ' . $project->name,
            'amount' => (float)$request->amount,
            'description' => ($request->type === 'funding' ? 'Funding' : 'Expense') . ' · ' . ($request->description ?: $project->name),
            'txn_date' => $txn->txn_date,
            'source_type' => 'project',
            'source_id' => $txn->id,
        ]);

        return redirect()->back()->with('message', get_phrase('Project transaction recorded.'));
    }

    public function projectDelete($id)
    {
        $project = SchoolProject::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        FinanceTransaction::where('source_type', 'project')
            ->whereIn('source_id', ProjectTransaction::where('project_id', $id)->pluck('id'))->delete();
        ProjectTransaction::where('project_id', $id)->delete();
        $project->delete();
        return redirect()->route('admin.finance.projects')->with('message', get_phrase('Project deleted.'));
    }

    /* ============================================================ DASHBOARD + REPORTS */

    public function dashboard()
    {
        $sid = $this->schoolId();
        $monthStart = strtotime(date('Y-m-01'));

        $collectedMonth = (float) FeePayment::where('school_id', $sid)->where('paid_on', '>=', $monthStart)->sum('amount');
        $outstanding = (float) Invoice::where('school_id', $sid)->sum('balance');
        $income = (float) FinanceTransaction::where('school_id', $sid)->where('type', 'income')->sum('amount');
        $expense = (float) FinanceTransaction::where('school_id', $sid)->where('type', 'expense')->sum('amount');
        $net = $income - $expense;

        $incomeByCat = FinanceTransaction::where('school_id', $sid)->where('type', 'income')
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $expenseByCat = FinanceTransaction::where('school_id', $sid)->where('type', 'expense')
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $recent = FinanceTransaction::where('school_id', $sid)->orderByDesc('id')->limit(8)->get();

        return view('admin.finance.dashboard', compact('collectedMonth', 'outstanding', 'income', 'expense', 'net', 'incomeByCat', 'expenseByCat', 'recent'));
    }

    private function range(Request $request, $defaultFrom = null)
    {
        $from = $request->from ? strtotime($request->from) : ($defaultFrom ?: strtotime(date('Y-01-01')));
        $to = $request->to ? strtotime($request->to . ' 23:59:59') : time();
        return [$from, $to];
    }

    public function reportIncome(Request $request)
    {
        $sid = $this->schoolId();
        [$from, $to] = $this->range($request);
        $income = FinanceTransaction::where('school_id', $sid)->where('type', 'income')->whereBetween('txn_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $expense = FinanceTransaction::where('school_id', $sid)->where('type', 'expense')->whereBetween('txn_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $totalIncome = (float) $income->sum('t');
        $totalExpense = (float) $expense->sum('t');
        $net = $totalIncome - $totalExpense;
        return view('admin.finance.report_income', compact('income', 'expense', 'totalIncome', 'totalExpense', 'net', 'from', 'to'));
    }

    public function reportCollection(Request $request)
    {
        $sid = $this->schoolId();
        $rows = Invoice::where('school_id', $sid)
            ->selectRaw('class_id, COUNT(*) as invoices, SUM(paid_amount + balance) as billed, SUM(paid_amount) as collected, SUM(balance) as outstanding')
            ->groupBy('class_id')->get();
        $totals = [
            'billed' => (float) $rows->sum('billed'),
            'collected' => (float) $rows->sum('collected'),
            'outstanding' => (float) $rows->sum('outstanding'),
        ];
        return view('admin.finance.report_collection', compact('rows', 'totals'));
    }

    public function reportDefaulters(Request $request)
    {
        $sid = $this->schoolId();
        $class_id = $request->class_id ?? '';
        $rows = Invoice::where('school_id', $sid)->where('balance', '>', 0)
            ->when($class_id !== '', fn($q) => $q->where('class_id', $class_id))
            ->orderByDesc('balance')->get();
        $classes = Classes::where('school_id', $sid)->get();
        $totalOutstanding = (float) $rows->sum('balance');
        return view('admin.finance.report_defaulters', compact('rows', 'classes', 'class_id', 'totalOutstanding'));
    }

    public function reportDaybook(Request $request)
    {
        $sid = $this->schoolId();
        [$from, $to] = $this->range($request, strtotime(date('Y-m-01')));
        $txns = FinanceTransaction::where('school_id', $sid)->whereBetween('txn_date', [$from, $to])
            ->orderBy('txn_date')->orderBy('id')->get();
        $income = (float) $txns->where('type', 'income')->sum('amount');
        $expense = (float) $txns->where('type', 'expense')->sum('amount');
        return view('admin.finance.report_daybook', compact('txns', 'income', 'expense', 'from', 'to'));
    }

    /* ---------- Period financial statements (monthly / quarterly / yearly I&E) ---------- */

    // Epoch [from, to] bounds + a human label for a calendar period.
    private function periodRange($period, $year, $month, $quarter)
    {
        if ($period === 'year') {
            $from  = strtotime("$year-01-01 00:00:00");
            $to    = strtotime("$year-12-31 23:59:59");
            $label = get_phrase('Year') . ' ' . $year;
        } elseif ($period === 'quarter') {
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = sprintf('%d-%02d-01 00:00:00', $year, $startMonth);
            $from  = strtotime($start);
            $to    = strtotime($start . ' +3 months -1 second');
            $label = 'Q' . $quarter . ' ' . $year;
        } else { // month
            $start = sprintf('%d-%02d-01 00:00:00', $year, $month);
            $from  = strtotime($start);
            $to    = strtotime($start . ' +1 month -1 second');
            $label = date('F Y', $from);
        }
        return [$from, $to, $label];
    }

    // Shared aggregation used by both the screen view and the PDF export.
    private function buildStatement(Request $request)
    {
        $sid     = $this->schoolId();
        $period  = in_array($request->period, ['month', 'quarter', 'year']) ? $request->period : 'month';
        $year    = (int) ($request->year ?: date('Y'));
        $month   = (int) ($request->month ?: date('n'));
        $quarter = (int) ($request->quarter ?: ceil(date('n') / 3));
        if ($month < 1 || $month > 12) $month = (int) date('n');
        if ($quarter < 1 || $quarter > 4) $quarter = (int) ceil(date('n') / 3);

        [$from, $to, $label] = $this->periodRange($period, $year, $month, $quarter);

        // I&E for the selected period (same shape as reportIncome)
        $income = FinanceTransaction::where('school_id', $sid)->where('type', 'income')->whereBetween('txn_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $expense = FinanceTransaction::where('school_id', $sid)->where('type', 'expense')->whereBetween('txn_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as t')->groupBy('category')->orderByDesc('t')->get();
        $totalIncome  = (float) $income->sum('t');
        $totalExpense = (float) $expense->sum('t');
        $net = $totalIncome - $totalExpense;

        // Month-by-month breakdown for the whole selected year (single pass, no N+1)
        $yStart = strtotime("$year-01-01 00:00:00");
        $yEnd   = strtotime("$year-12-31 23:59:59");
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) $monthly[$m] = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
        $yearTxns = FinanceTransaction::where('school_id', $sid)->whereBetween('txn_date', [$yStart, $yEnd])
            ->get(['type', 'amount', 'txn_date']);
        foreach ($yearTxns as $t) {
            $m = (int) date('n', (int) $t->txn_date);
            $monthly[$m][$t->type === 'expense' ? 'expense' : 'income'] += (float) $t->amount;
        }
        foreach ($monthly as $m => &$row) $row['net'] = $row['income'] - $row['expense'];
        unset($row);

        // quarter subtotals + year totals derived from the monthly buckets
        $quarterly = [];
        for ($q = 1; $q <= 4; $q++) $quarterly[$q] = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
        $yearTotals = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
        foreach ($monthly as $m => $row) {
            $q = (int) ceil($m / 3);
            foreach (['income', 'expense', 'net'] as $k) {
                $quarterly[$q][$k] += $row[$k];
                $yearTotals[$k]   += $row[$k];
            }
        }

        // years present in the ledger, for the picker (fallback to current year)
        $years = FinanceTransaction::where('school_id', $sid)
            ->selectRaw('DISTINCT FROM_UNIXTIME(txn_date, "%Y") as y')->orderByDesc('y')->pluck('y')
            ->map(fn ($y) => (int) $y)->filter()->values()->all();
        if (!in_array($year, $years)) $years[] = $year;
        rsort($years);

        return compact(
            'period', 'year', 'month', 'quarter', 'from', 'to', 'label',
            'income', 'expense', 'totalIncome', 'totalExpense', 'net',
            'monthly', 'quarterly', 'yearTotals', 'years'
        );
    }

    public function statements(Request $request)
    {
        return view('admin.finance.statements', $this->buildStatement($request));
    }

    public function statementsPdf(Request $request)
    {
        $data = $this->buildStatement($request);

        $school   = \DB::table('schools')->where('id', $this->schoolId())->first();
        $logoFile = get_settings('dark_logo');
        $logoPath = $logoFile ? public_path('assets/uploads/logo/' . $logoFile) : null;
        if ($logoPath && !file_exists($logoPath)) $logoPath = null;
        $data['school']   = $school;
        $data['logoPath'] = $logoPath;

        $pdf = \PDF::loadView('admin.finance.statement_pdf', $data);
        $pdf->setPaper('a4');
        return $pdf->download('Financial-Statement-' . str_replace(' ', '-', $data['label']) . '.pdf');
    }

    /* ============================================================ EXPENSES */

    public function expenses()
    {
        $sid = $this->schoolId();
        $expenses = ExpenseRecord::where('school_id', $sid)->orderByDesc('expense_date')->orderByDesc('id')->paginate(15);
        $categories = ExpenseRecord::where('school_id', $sid)->distinct()->pluck('category')->filter()->values();
        $total = (float) ExpenseRecord::where('school_id', $sid)->sum('amount');
        $accounts = Account::where('school_id', $sid)->orderBy('name')->get();
        return view('admin.finance.expenses', compact('expenses', 'categories', 'total', 'accounts'));
    }

    public function expenseStore(Request $request)
    {
        $request->validate(['category' => 'required|string|max:150', 'amount' => 'required|numeric|min:0.01']);

        $attachment = null;
        if ($request->hasFile('attachment')) {
            $f = $request->file('attachment');
            $attachment = time() . '_' . preg_replace('/\s+/', '_', $f->getClientOriginalName());
            $f->move(public_path('assets/uploads/expenses/'), $attachment);
        }

        $exp = ExpenseRecord::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'category' => $request->category,
            'vendor' => $request->vendor,
            'amount' => (float) $request->amount,
            'description' => $request->description,
            'expense_date' => $request->expense_date ? strtotime($request->expense_date) : time(),
            'attachment' => $attachment,
            'recorded_by' => auth()->user()->id,
        ]);

        FinanceTransaction::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'type' => 'expense',
            'category' => $request->category,
            'amount' => (float) $request->amount,
            'description' => trim(($request->vendor ? $request->vendor . ' · ' : '') . ($request->description ?: $request->category)),
            'txn_date' => $exp->expense_date,
            'source_type' => 'expense_record',
            'source_id' => $exp->id,
            'account_id' => $this->ownAccountId($request->account_id),
        ]);

        return redirect()->back()->with('message', get_phrase('Expense recorded.'));
    }

    public function expenseDelete($id)
    {
        $exp = ExpenseRecord::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        FinanceTransaction::where('source_type', 'expense_record')->where('source_id', $id)->delete();
        $exp->delete();
        return redirect()->back()->with('message', get_phrase('Expense deleted.'));
    }

    /* ============================================================ OTHER INCOME */

    public function incomes()
    {
        $sid = $this->schoolId();
        $incomes = IncomeRecord::where('school_id', $sid)->orderByDesc('income_date')->orderByDesc('id')->paginate(15);
        $sources = IncomeRecord::where('school_id', $sid)->distinct()->pluck('source')->filter()->values();
        $total = (float) IncomeRecord::where('school_id', $sid)->sum('amount');
        $accounts = Account::where('school_id', $sid)->orderBy('name')->get();
        return view('admin.finance.incomes', compact('incomes', 'sources', 'total', 'accounts'));
    }

    public function incomeStore(Request $request)
    {
        $request->validate(['source' => 'required|string|max:150', 'amount' => 'required|numeric|min:0.01']);

        $inc = IncomeRecord::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'source' => $request->source,
            'payer' => $request->payer,
            'amount' => (float) $request->amount,
            'description' => $request->description,
            'income_date' => $request->income_date ? strtotime($request->income_date) : time(),
            'recorded_by' => auth()->user()->id,
        ]);

        FinanceTransaction::create([
            'school_id' => $this->schoolId(),
            'session_id' => $this->activeSession(),
            'type' => 'income',
            'category' => $request->source,
            'amount' => (float) $request->amount,
            'description' => trim(($request->payer ? $request->payer . ' · ' : '') . ($request->description ?: $request->source)),
            'txn_date' => $inc->income_date,
            'source_type' => 'income_record',
            'source_id' => $inc->id,
            'account_id' => $this->ownAccountId($request->account_id),
        ]);

        return redirect()->back()->with('message', get_phrase('Income recorded.'));
    }

    public function incomeDelete($id)
    {
        $inc = IncomeRecord::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        FinanceTransaction::where('source_type', 'income_record')->where('source_id', $id)->delete();
        $inc->delete();
        return redirect()->back()->with('message', get_phrase('Income deleted.'));
    }

    /* ============================================================ PAYROLL */

    private function staffQuery()
    {
        return User::where('school_id', $this->schoolId())->whereIn('role_id', [2, 3, 4, 5]);
    }

    private function lines($names, $amounts)
    {
        $out = [];
        foreach (($names ?? []) as $i => $n) {
            $n = trim((string) $n);
            $a = (float) ($amounts[$i] ?? 0);
            if ($n === '' || $a <= 0) continue;
            $out[] = ['name' => $n, 'amount' => $a];
        }
        return $out;
    }

    public function payroll()
    {
        $staff = $this->staffQuery()->orderBy('name')->get();
        $structures = SalaryStructure::where('school_id', $this->schoolId())->get()->keyBy('staff_id');
        return view('admin.finance.payroll', compact('staff', 'structures'));
    }

    public function salaryForm($staff_id)
    {
        $staff = $this->staffQuery()->where('id', $staff_id)->firstOrFail();
        $structure = SalaryStructure::where('school_id', $this->schoolId())->where('staff_id', $staff_id)->first();
        return view('admin.finance.salary_form', compact('staff', 'structure'));
    }

    public function salarySave(Request $request, $staff_id)
    {
        $this->staffQuery()->where('id', $staff_id)->firstOrFail();
        $request->validate(['basic_salary' => 'required|numeric|min:0']);

        $allowances = $this->lines($request->allow_name, $request->allow_amount);
        $deductions = $this->lines($request->deduct_name, $request->deduct_amount);
        $net = (float) $request->basic_salary + array_sum(array_column($allowances, 'amount')) - array_sum(array_column($deductions, 'amount'));

        SalaryStructure::updateOrCreate(
            ['school_id' => $this->schoolId(), 'staff_id' => $staff_id],
            ['basic_salary' => (float) $request->basic_salary, 'allowances' => $allowances, 'deductions' => $deductions, 'net_pay' => $net]
        );
        return redirect()->route('admin.finance.payroll')->with('message', get_phrase('Salary structure saved.'));
    }

    public function payslips(Request $request)
    {
        $month = $request->month ?: date('Y-m');
        $slips = Payslip::where('school_id', $this->schoolId())->where('month', $month)->get();
        $staffById = User::whereIn('id', $slips->pluck('staff_id'))->get()->keyBy('id');
        $totals = [
            'net' => (float) $slips->sum('net_pay'),
            'paid' => (float) $slips->where('status', 'paid')->sum('net_pay'),
        ];
        $structuredCount = SalaryStructure::where('school_id', $this->schoolId())->count();
        return view('admin.finance.payslips', compact('slips', 'staffById', 'month', 'totals', 'structuredCount'));
    }

    public function generatePayslips(Request $request)
    {
        $month = $request->month ?: date('Y-m');
        $structures = SalaryStructure::where('school_id', $this->schoolId())->get();
        $made = 0;
        foreach ($structures as $s) {
            if (Payslip::where('school_id', $this->schoolId())->where('staff_id', $s->staff_id)->where('month', $month)->exists()) continue;
            Payslip::create([
                'school_id' => $this->schoolId(),
                'staff_id' => $s->staff_id,
                'month' => $month,
                'basic' => $s->basic_salary,
                'allowances_total' => array_sum(array_column($s->allowances ?? [], 'amount')),
                'deductions_total' => array_sum(array_column($s->deductions ?? [], 'amount')),
                'net_pay' => $s->net_pay,
                'allowances' => $s->allowances,
                'deductions' => $s->deductions,
                'status' => 'pending',
            ]);
            $made++;
        }
        return redirect()->route('admin.finance.payslips', ['month' => $month])
            ->with('message', $made . ' ' . get_phrase('payslips generated for') . ' ' . $month);
    }

    public function payslipPay($id)
    {
        $slip = Payslip::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        if ($slip->status !== 'paid') {
            $slip->update(['status' => 'paid', 'paid_on' => time()]);
            $staff = User::find($slip->staff_id);
            FinanceTransaction::create([
                'school_id' => $this->schoolId(),
                'session_id' => $this->activeSession(),
                'type' => 'expense',
                'category' => 'Salaries',
                'amount' => (float) $slip->net_pay,
                'description' => 'Salary · ' . (optional($staff)->name ?? 'Staff') . ' · ' . $slip->month,
                'txn_date' => time(),
                'source_type' => 'payslip',
                'source_id' => $slip->id,
            ]);
        }
        return redirect()->back()->with('message', get_phrase('Payslip marked paid.'));
    }

    public function payslipShow($id)
    {
        $slip = Payslip::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $staff = User::find($slip->staff_id);
        $school = \DB::table('schools')->where('id', $this->schoolId())->first();
        return view('admin.finance.payslip_show', compact('slip', 'staff', 'school'));
    }

    public function payslipDelete($id)
    {
        $slip = Payslip::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        FinanceTransaction::where('source_type', 'payslip')->where('source_id', $id)->delete();
        $slip->delete();
        return redirect()->back()->with('message', get_phrase('Payslip deleted.'));
    }

    /* ============================================================ ACCOUNTS + TRANSFERS */

    private function accountBalance($account)
    {
        $sid = $this->schoolId();
        $inc = (float) FinanceTransaction::where('school_id', $sid)->where('account_id', $account->id)->where('type', 'income')->sum('amount');
        $exp = (float) FinanceTransaction::where('school_id', $sid)->where('account_id', $account->id)->where('type', 'expense')->sum('amount');
        $in = (float) AccountTransfer::where('school_id', $sid)->where('to_account_id', $account->id)->sum('amount');
        $out = (float) AccountTransfer::where('school_id', $sid)->where('from_account_id', $account->id)->sum('amount');
        return (float) $account->opening_balance + $inc - $exp + $in - $out;
    }

    public function accounts()
    {
        $accounts = Account::where('school_id', $this->schoolId())->orderBy('name')->get();
        $accounts->each(fn($a) => $a->balance = $this->accountBalance($a));
        $totalCash = $accounts->sum('balance');
        return view('admin.finance.accounts', compact('accounts', 'totalCash'));
    }

    public function accountStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:150']);
        Account::create([
            'school_id' => $this->schoolId(),
            'name' => $request->name,
            'type' => $request->type ?: 'bank',
            'opening_balance' => (float) ($request->opening_balance ?: 0),
        ]);
        return redirect()->back()->with('message', get_phrase('Account added.'));
    }

    public function accountDelete($id)
    {
        Account::where('id', $id)->where('school_id', $this->schoolId())->delete();
        return redirect()->back()->with('message', get_phrase('Account deleted.'));
    }

    public function transfers()
    {
        $sid = $this->schoolId();
        $accounts = Account::where('school_id', $sid)->orderBy('name')->get();
        $transfers = AccountTransfer::where('school_id', $sid)->orderByDesc('transfer_date')->orderByDesc('id')->paginate(15);
        return view('admin.finance.transfers', compact('accounts', 'transfers'));
    }

    public function transferStore(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|different:to_account_id',
            'to_account_id' => 'required',
            'amount' => 'required|numeric|min:0.01',
        ]);
        // H10: both accounts must belong to this school (blocks cross-school transfer IDOR).
        abort_if(Account::whereIn('id', [$request->from_account_id, $request->to_account_id])
            ->where('school_id', $this->schoolId())->count() < 2, 403);
        AccountTransfer::create([
            'school_id' => $this->schoolId(),
            'from_account_id' => $request->from_account_id,
            'to_account_id' => $request->to_account_id,
            'amount' => (float) $request->amount,
            'description' => $request->description,
            'transfer_date' => $request->transfer_date ? strtotime($request->transfer_date) : time(),
        ]);
        return redirect()->back()->with('message', get_phrase('Transfer recorded.'));
    }

    public function transferDelete($id)
    {
        AccountTransfer::where('id', $id)->where('school_id', $this->schoolId())->delete();
        return redirect()->back()->with('message', get_phrase('Transfer deleted.'));
    }
}
