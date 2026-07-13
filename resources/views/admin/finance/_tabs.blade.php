@php $rt = request()->path(); @endphp
<ul class="nav eTab-nav mb-3" style="gap:8px; flex-wrap:wrap;">
  <li><a class="eBtn {{ str_contains($rt,'reports/income') ? 'btn-primary':'btn-secondary' }}" href="{{ route('admin.finance.report.income') }}">{{ get_phrase('Income statement') }}</a></li>
  <li><a class="eBtn {{ str_contains($rt,'reports/collection') ? 'btn-primary':'btn-secondary' }}" href="{{ route('admin.finance.report.collection') }}">{{ get_phrase('Fee collection') }}</a></li>
  <li><a class="eBtn {{ str_contains($rt,'reports/defaulters') ? 'btn-primary':'btn-secondary' }}" href="{{ route('admin.finance.report.defaulters') }}">{{ get_phrase('Defaulters') }}</a></li>
  <li><a class="eBtn {{ str_contains($rt,'reports/daybook') ? 'btn-primary':'btn-secondary' }}" href="{{ route('admin.finance.report.daybook') }}">{{ get_phrase('Cash daybook') }}</a></li>
  <li><a class="eBtn {{ str_contains($rt,'finance/statements') ? 'btn-primary':'btn-secondary' }}" href="{{ route('admin.finance.statements') }}">{{ get_phrase('Financial statements') }}</a></li>
</ul>
