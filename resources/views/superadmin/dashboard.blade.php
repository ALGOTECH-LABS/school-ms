@extends('superadmin.navigation')
@section('content')   
@php

$month_wise_payment = array('Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0, 'May' => 0, 'Jun' => 0, 'Jul' => 0, 'Aug' => 0, 'Sep' => 0, 'Oct' => 0, 'Nov' => 0, 'Dec' => 0);
$month_wise_payment_for_json = array();

foreach($month_wise_payment as $month => $payment){
    $current_year = date('Y');
    $start_date = strtotime('1 '.$month.' '.$current_year);
    $end_date = strtotime(date('t M Y', $start_date));
    $total_payment_in_this_month = DB::table('subscriptions')->where('date_added', '>=', $start_date)->where('date_added', '<=', $end_date)->sum('paid_amount');

    array_push($month_wise_payment_for_json, array("month" => $month, "amount" => $total_payment_in_this_month));
}

$month_wise_payment = $month_wise_payment_for_json;
@endphp
<!-- Mani section header and breadcrumb -->
<div class="mainSection-title">
	<div class="row">
		<div class="col-12">
			<div
				class="d-flex justify-content-between align-items-center flex-wrap gr-15"
				>
				<div class="d-flex flex-column">
					<h4>{{ get_phrase('Dashboard') }}</h4>
					<ul class="d-flex align-items-center eBreadcrumb-2">
						<li><a href="#">{{ get_phrase('Home') }}</a></li>
						<li><a href="#">{{ get_phrase('Dashboard') }}</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Start Alerts -->
<div class="row">
	<div class="col-12">
		<div class="eSection-dashboardItems">
			<div class="row flex-wrap">
				<!-- Dashboard Short Details -->
				<div class="col-lg-5">
					<div class="dashboard_ShortListItems">
						<div class="row">
							<div class="col-md-12">
								<div class="dashboard_ShortListItem">
									<div
										class="dsHeader d-flex justify-content-between align-items-center"
										>
										<h5 class="title">{{ get_phrase('Schools') }}</h5>
										<a href="{{ route('superadmin.school.list') }}" class="ds_link ds_sutdent">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												width="10.146"
												height="4.764"
												viewBox="0 0 10.146 4.764"
												>
												<path
													id="Read_more_icon"
													data-name="Read more icon"
													d="M11.337,5.978l-.84.84.941.947H3.573V8.955h7.865L10.5,9.9l.84.846L13.719,8.36Z"
													transform="translate(-3.573 -5.978)"
													fill="#000000"
													/>
											</svg>
										</a>
									</div>
									<div class="dsBody d-flex justify-content-between align-items-center" >
										<div class="ds_item_details">
											<h4 class="total_no">{{ DB::table('schools')->get()->count() }}</h4>
											<p class="total_info">{{ get_phrase('Total Schools') }}</p>
										</div>
										<div class="ds_item_icon">
											<img
												src="{{ asset('assets/images/Student_icon.png') }}"
												alt=""
												/>
										</div>
									</div>
								</div>
							
								<div class="dashboard_ShortListItem mt-3">
									<div
										class="dsHeader d-flex justify-content-between align-items-center"
										>
										<h5 class="title">{{ get_phrase('Subscription') }}</h5>
										<a href="{{ route('superadmin.subscription.report') }}" class="ds_link ds_parent">
											<svg
												xmlns="http://www.w3.org/2000/svg"
												width="10.146"
												height="4.764"
												viewBox="0 0 10.146 4.764"
												>
												<path
													id="Read_more_icon"
													data-name="Read more icon"
													d="M11.337,5.978l-.84.84.941.947H3.573V8.955h7.865L10.5,9.9l.84.846L13.719,8.36Z"
													transform="translate(-3.573 -5.978)"
													fill="#000000"
													/>
											</svg>
										</a>
									</div>
									<div class="dsBody d-flex justify-content-between align-items-center" >
										<div class="ds_item_details">
											<h4 class="total_no">{{ DB::table('subscriptions')->where('expire_date', '>=', time())->get()->count() }}</h4>
											<p class="total_info">{{ get_phrase('Total Active Subscription') }}</p>
										</div>
										<div class="ds_item_icon">
											<img
												src="{{ asset('assets/images/Parents_icon.png') }}"
												alt=""
												/>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Imcome Report -->
				<!-- Upcoming Events -->
				<div class="col-md-7">
                    <div class="card bg-info">
                        <h6 class="ms-4 mt-4 mb-5 text-white">{{ get_phrase('Subscription Payment') }}</h6>
                        <div id="chartdiv" class="chartdiv"></div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- ===================== Website Visits ===================== -->
@php $maxV = max(1, collect($daily ?? [])->max('count') ?: 1); @endphp
<div class="row">
  <div class="col-12">
    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
      <h5 class="mb-0" style="font-weight:700;color:#2b2f3a;"><i class="bi bi-graph-up-arrow me-2" style="color:#00955f;"></i>{{ get_phrase('Website Visits') }}</h5>
      <span class="text-muted" style="font-size:12.5px;">{{ get_phrase('Login / landing page traffic') }}</span>
    </div>
  </div>

  {{-- KPI cards --}}
  @php
    $cards = [
      ['label'=>'Total Visits','value'=>number_format($visits['total'] ?? 0),'icon'=>'bi-eye-fill','c'=>'#00955f'],
      ['label'=>'Today','value'=>number_format($visits['today'] ?? 0),'icon'=>'bi-calendar-day','c'=>'#2f6fb0'],
      ['label'=>'Last 7 Days','value'=>number_format($visits['week'] ?? 0),'icon'=>'bi-calendar-week','c'=>'#f04b24'],
      ['label'=>'Unique Visitors','value'=>number_format($visits['unique'] ?? 0),'icon'=>'bi-people-fill','c'=>'#7952b3'],
    ];
  @endphp
  @foreach($cards as $card)
  <div class="col-lg-3 col-md-6 mb-3">
    <div style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:16px;height:100%;">
      <span style="flex:none;width:52px;height:52px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:22px;color:{{ $card['c'] }};background:{{ $card['c'] }}1a;">
        <i class="bi {{ $card['icon'] }}"></i>
      </span>
      <div>
        <div style="font-size:24px;font-weight:800;color:#2b2f3a;line-height:1;">{{ $card['value'] }}</div>
        <div style="font-size:12.5px;color:#8b93a0;margin-top:4px;">{{ get_phrase($card['label']) }}</div>
      </div>
    </div>
  </div>
  @endforeach

  {{-- 7-day bar chart --}}
  <div class="col-lg-7 mb-3">
    <div style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:20px 22px;height:100%;">
      <div style="font-size:14px;font-weight:700;color:#2b2f3a;margin-bottom:18px;">{{ get_phrase('Visits — last 7 days') }}</div>
      <div style="display:flex;align-items:flex-end;gap:14px;height:150px;">
        @foreach(($daily ?? []) as $d)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
          <div style="font-size:12px;font-weight:700;color:#00955f;margin-bottom:5px;">{{ $d['count'] }}</div>
          <div title="{{ $d['date'] }}" style="width:100%;max-width:38px;border-radius:6px 6px 0 0;background:linear-gradient(180deg,#00955f,#00744a);height:{{ max(4, round(($d['count']/$maxV)*115)) }}px;"></div>
          <div style="font-size:11.5px;color:#98a0aa;margin-top:8px;">{{ $d['label'] }}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- recent visits --}}
  <div class="col-lg-5 mb-3">
    <div style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:20px 22px;height:100%;">
      <div style="font-size:14px;font-weight:700;color:#2b2f3a;margin-bottom:12px;">{{ get_phrase('Recent visits') }}</div>
      @forelse(($recentVisits ?? []) as $v)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f5f4;">
          <div style="min-width:0;">
            <div style="font-size:13px;color:#2b2f3a;font-weight:600;">{{ $v->ip_address ?: '—' }}</div>
            <div style="font-size:11.5px;color:#98a0aa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px;">{{ \Illuminate\Support\Str::limit($v->user_agent, 42) ?: get_phrase('Unknown device') }}</div>
          </div>
          <div style="font-size:11.5px;color:#8b93a0;white-space:nowrap;">{{ $v->created_at ? $v->created_at->diffForHumans() : '' }}</div>
        </div>
      @empty
        <p class="text-muted mb-0" style="font-size:13px;">{{ get_phrase('No visits recorded yet.') }}</p>
      @endforelse
    </div>
  </div>
</div>

<!-- Resources -->
<script src="{{asset('assets/amchart/index.js')}}"></script>
<script src="{{asset('assets/amchart/xy.js')}}"></script>
<script src="{{asset('assets/amchart/animated.js')}}"></script>
<!-- Chart code -->
<script>
	"use strict";
	
	am5.ready(function() {
	
	// Create root element
	var root = am5.Root.new("chartdiv");
	
	
	// Set themes
	root.setThemes([
	  am5themes_Animated.new(root)
	]);
	
	
	// Create chart
	var chart = root.container.children.push(am5xy.XYChart.new(root, {
	  panX: true,
	  panY: true,
	  wheelX: "panX",
	  wheelY: "zoomX",
	  pinchZoomX:true
	}));
	
	// Add cursor
	var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {}));
	cursor.lineY.set("visible", false);
	
	
	// Create axes
	var xRenderer = am5xy.AxisRendererX.new(root, { minGridDistance: 30 });
	xRenderer.labels.template.setAll({
	  rotation: -90,
	  centerY: am5.p50,
	  centerX: am5.p100,
	  paddingRight: 15
	});
	
	var xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
	  maxDeviation: 0.3,
	  categoryField: "month",
	  renderer: xRenderer,
	  tooltip: am5.Tooltip.new(root, {})
	}));
	
	var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
	  maxDeviation: 0.3,
	  renderer: am5xy.AxisRendererY.new(root, {})
	}));
	
	
	// Create series
	var series = chart.series.push(am5xy.ColumnSeries.new(root, {
	  name: "Series 1",
	  xAxis: xAxis,
	  yAxis: yAxis,
	  valueYField: "amount",
	  sequencedInterpolation: true,
	  categoryXField: "month",
	  tooltip: am5.Tooltip.new(root, {
	    labelText:"{valueY}"
	  })
	}));
	
	series.columns.template.setAll({ cornerRadiusTL: 5, cornerRadiusTR: 5 });
	series.columns.template.adapters.add("fill", function(fill, target) {
	  return chart.get("colors").getIndex(series.columns.indexOf(target));
	});
	
	series.columns.template.adapters.add("stroke", function(stroke, target) {
	  return chart.get("colors").getIndex(series.columns.indexOf(target));
	});
	
	
	// Set data
	var data = <?php echo json_encode($month_wise_payment); ?>;
	
	xAxis.data.setAll(data);
	series.data.setAll(data);
	
	
	// Make stuff animate on load
	// https://www.amcharts.com/docs/v5/concepts/animations/
	series.appear(1000);
	chart.appear(1000, 100);
	
	}); // end am5.ready()
</script>
<!-- HTML -->
@endsection