@extends('layout')

@section('meta')
	<meta name="Description" content="Tell us which MTG cards are strictly better than others">
	<title>StrictlyBetter - Add Suggestion</title>
@stop

@section('head')
	<style>
		.select2-selection {
			height: 186px !important;
			background-color: whitesmoke !important;
		}
		ul.select2-results__options {
			max-height: 600px !important;
			height: 50% !important;
		}
		.select2-selection__arrow {
			height: 186px !important;
		}
		.select2-selection__arrow b {
			border-width: 10px 10px 0 10px !important;
			margin-left: -26px !important;
			margin-top: -8px !important;
		}
		.select2-container--default.select2-container--open .select2-selection__arrow b {
			border-width: 0 10px 10px 10px !important;
		}

		.select2-results__option.loading-results,
		.select2-results__option.select2-results__option--load-more {
			display: none;
			/*
			background-image: url('/images/loading.gif');
			background-repeat: no-repeat;
			padding-left: 35px;
			background-position: 10px 50%;
			*/
		}
	</style>
@stop

@section('content')
	<div class="container">
		<h1>Add Suggestion</h1>
		{{ Form::open(['route' => 'card.store']) }}

			<div class="row" style="min-height:220px"> 
				<div class="form-group col-lg-5">
					<label for="inferior" style="font-size:larger;">Current Card</label>
					{{ Form::select('inferior', [], null, ['id' => 'inferior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
				</div>

				<div id="upgrade_sign_wrapper" class="col-lg-1 text-center" style="margin: auto">
					<i class="fa fa-arrow-right" style="font-size:30px;"></i>
				</div>

				<div class="form-group col-lg-5">
					<label for="superior" style="font-size:larger;">Strictly Better Card</label>
					{{ Form::select('superior', [], null, ['id' => 'superior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
				</div>
			

			<div class="form-group col-lg-1" style="margin: auto">
				{{ Form::submit('Add', ['class' => 'btn btn-primary btn-lg']) }}
			</div>
			</div>
		{{ Form::close() }}
		<div>
			<br>
		</div>
	</div>

	<div class="container collapse-inferior" id="upgrade_view_container">
		<div class="row" id="upgrade_view">
			@if($card)
				@include('card.partials.upgrade')
			@endif
		</div>
	</div>

@stop

@section('js')
<script>

var inferiors = {!! json_encode($inferiors) !!};
var superiors = {!! json_encode($superiors) !!};

var window_width = $(window).width();

$(document).ready(function() { 

	function select2_template(data) {

		var template = $('<span class="row">');
		var text = $('<span class="mtgcard-select-text">');

		var name = $('<strong>').text(data.text + "\n");
		text.append(name);

		if (data.typeline) {
			var typeline = $('<i>').text(data.typeline);
			text.append(typeline);
		}

		var img = $('<img class="mtgcard-thumb">');
		img.attr('src', (data.imageUrl ? data.imageUrl : "{{ asset('image/card-back.jpg') }}"));

		template.append(img);
		template.append(text);

		return template[0].outerHTML;
	}

	var select2_options = {
		allowClear: true, 
		placeholder: "Select card",
		minimumInputLength: 2,
		data: [],
		ajax: {
			url: "{{ route('card.autocomplete') }}",
			dataType: 'json',
			delay: 50,
			data: function (params) {
				return {
					term: params.term,
					page: params.page || 1,
					select2: true
				};
			},
			processResults: function (data, params) {

				params.page = params.page || 1;

				return { 
					results: data, 
					pagination: {
						more: data.length >= 25
					}
				};
			}
		},
		escapeMarkup: function(markup) {
			return markup;
		},
		templateResult: function(data) {
			return select2_template(data);
		},
		templateSelection: function(data) {
			return select2_template(data);
		}
	};

	select2_options.data = inferiors;
	$("#inferior").select2(select2_options).on('select2:select', function (e) {

		$.ajax({
			type: "GET",
			url: "{{ route('index') }}/upgradeview/" + e.params.data.id,
			dataType: "html",
			success: function(response) {
				$("#upgrade_view").html(response);
				register_card_image_handlers('#upgrade_view');
			}
		});
	});
	
	select2_options.data = superiors;
	select2_options.placeholder = "Select strictly better card";
	$("#superior").select2(select2_options);

	// Rebuild on resize for responsive design
	window.addEventListener('resize', function(event){

		// Skip if width wasn't changed. Also fixes touchscreend devices, that pop keyboard when select2 is opened
		if (window_width == $(window).width())
			return;
		
		window_width = $(window).width();

		select2_options.data = inferiors;
		select2_options.placeholder = "Select card";

		$("#inferior").select2(select2_options);

		select2_options.data = superiors;
		select2_options.placeholder = "Select strictly better card";

		$("#superior").select2(select2_options);
	});

	$(".tell_superior").on('click', function(event) {
		event.preventDefault();
		$("#superior").select2('open');
	});
});
</script>
@stop