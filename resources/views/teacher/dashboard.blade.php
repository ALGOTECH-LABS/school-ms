@extends('teacher.navigation')
@section('content')

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
            <div class="col-lg-12">
                <div class="dashboard_ShortListItem">
                        <h4 class="text-dark">{{ auth()->user()->name }}</h4>
                        <p>Welcome, to {{ DB::table('schools')->where('id', auth()->user()->school_id)->value('title') }}</p>
                </div>
            </div>
             <!-- My timetable this week -->
             <div class="col-lg-8">
               <div class="eSection-wrap">
                 <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
                   <h5 class="mb-0"><i class="bi bi-calendar-week me-2" style="color:#00955f;"></i>{{ get_phrase('My timetable this week') }}</h5>
                   <a class="eBtn btn-secondary" href="{{ route('teacher.timetable') }}"><i class="bi bi-arrows-fullscreen"></i> {{ get_phrase('Full timetable') }}</a>
                 </div>
                 @include('partials.timetable', ['routines' => $routines, 'cellShow' => 'class', 'admin' => false])
               </div>
             </div>

             <!-- Upcoming Events -->
             <div class="col-lg-4">
               <div class="dashboard_report dashboard_upcoming_events">
                 <div
                   class="ds_report_header d-flex justify-content-between align-items-start"
                 >
                   <div class="ds_report_left">
                     <h4 class="title">{{ get_phrase('Upcoming Events') }}</h4>
                   </div>
                   
                 </div>
                 <div class="ds_report_list pt-38">
                   <ul class="upcoming_events_items d-flex flex-column">
   
                       @php $upcoming_events = DB::table('frontend_events')->where('school_id', auth()->user()->school_id)->where('timestamp', '>', time())->where('status', 1)->take(3)->orderBy('id', 'DESC')->get(); @endphp
                       @foreach($upcoming_events as $upcoming_event)
                       <li>
                           <div
                           class="upcoming_events_item d-flex justify-content-between align-items-start"
                           >
                           <div class="events_info">
                               <a href="#" class="title">{{ $upcoming_event->title }}</a>
                               <p class="date">{{ date('D, M d Y', $upcoming_event->timestamp) }}</p>
                           </div>
                           
                           </div>
                       </li>
                       @endforeach
                   </ul>
                   <div class="text-end">
                     <a href="{{ route('teacher.events.list') }}" class="all_report_btn_2">{{ get_phrase('See all') }}</a>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>
       </div>
       </div>
   

@endsection
