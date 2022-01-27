@extends('layout')

@section('meta')
	<meta name="Description" content="Help by voting for cards others have suggested">
	<title>Strictly Better - Help Voting</title>
	<style>
		.categorylist {
			list-style-type: none;
			margin: 0px 0px 0px 0px;
			padding: 0;
			overflow: hidden;
			/*background-color: #333333;*/
		}

		.categorylist li {
			float: left;
			margin:  8px 16px 0px 0px;
			text-align: center;
			display: block;
		}

		.categorylist li a {
			color: #007bff;
			text-decoration: none;
		}

		.categorylist li a.active {
			color: black;
			text-decoration: underline;
		}

		.categorylist li a:hover {
			text-decoration: underline;
		}

		.btn-refresh {
			padding:  1px 10px 1px 10px	!important;
		}

		.btn-refresh i {
			font-size: 16px;
		}
	</style>
@stop

@section('content')

	<div class="container">
		<h1>Help Voting</h1>

		<p>Voting helps determine validity of the suggestions for users and AI alike.</p>

		<ul class="categorylist">
			<li><a class="{{ Request::is('votehelp/low-on-votes') ? 'active' : '' }}" href="{{ route('votehelp.low-on-votes') }}" 
				title="Help determine if fresh suggestions with low vote count are valid or not">📉 Low on Votes</a></li>
			<li><a class="{{ Request::is('votehelp/disputed') ? 'active' : '' }}" href="{{ route('votehelp.disputed') }}" 
				title="Help find a final verdict for disputed suggestions with similar amount of upvotes and downvotes">&#x2694; Disputed</a></li>
			<li><a class="{{ Request::is('votehelp/spreadsheets') ? 'active' : '' }}" href="{{ route('votehelp.spreadsheets') }}"
				title="Help validate suggestions people have listed elsewhere">&#127760; External Sources</a></li>
			<li>
				<a class="btn btn-light btn-gatherer btn-refresh" href="javascript:location.reload();">
					<i class="fa fa-refresh"></i>
					<span class="vote-text"></span>
				</a>
			</li>
		</ul>

		<br>
		@if(Request::is('votehelp/low-on-votes'))
			<p>Help determine if fresh suggestions with low vote count are valid or not.</p>
		@elseif(Request::is('votehelp/disputed'))
			<p>Help find a final verdict for disputed suggestions with similar amount of upvotes and downvotes.</p>
		@elseif(Request::is('votehelp/spreadsheets'))
			<p>Help validate suggestions people have listed elsewhere. Do you have such a list? Email me: <a href="mailto:{{ config('externals.author_email') }}">{{ config('externals.author_email') }}</a></p>
		@endif

		<hr>
		
		@if($inferior && $superior)
		
		<p>Instead of <b>{{ $inferior->name }}</b> should I play <b>{{ $superior->name }}</b> ?</p>

		<div class="row">
			
			<div class="col-sm-3">
				<div class="row" style="position:relative">
					@include('card.partials.maincard', ['card' => $inferior])
				</div>
			</div>
			<div class="col-sm-9">
				<div class="row" style="position:relative">
					@include('card.partials.relatedcard', ['related' => $superior, 'type' => 'superior'])
				</div>
			</div>
			
		</div>
		@else
			<p>Unfortunately, we couldn't find any suggestions to vote for in this category.</p>
		@endif
	</div>

@stop

@section('js')
<script>
	set_vote_refreshing(true);
</script>
@stop