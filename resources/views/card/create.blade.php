@extends('layout')

@section('content')
	
	<h1>Add Suggestion</h1>
	{{ Form::open(['route' => 'card.store']) }}

		<div class="row" style="height:220px"> 
			<div class="form-group col-md-5">
				<label for="inferior">Inferior Card</label>
				{{ Form::select('inferior', isset($inferiors) ? $inferiors : [], null, ['id' => 'inferior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
			</div>

			<div id="upgrade_sign_wrapper" class="col-md-1 text-center" style="padding-top: 32px;">
				<i class="fa fa-arrow-right" style="font-size:30px;"></i>
			</div>

			<div class="form-group col-md-5">
				<label for="superior">Superior Card</label>
				{{ Form::select('superior', isset($superiors) ? $superiors : [], null, ['id' => 'superior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
			</div>
		

		<div class="form-group col-md-1" style="padding-top: 32px">
			{{ Form::submit('Add', ['class' => 'btn btn-primary align-self-bottom']) }}
		</div>
		</div>
	{{ Form::close() }}
	<br>

	@if($card)
		@include('card.partials.upgrade')
	@endif

@stop

@section('js')
<script>
$(document).ready(function() { 

	function select2_template(data) {
		if (!data.imageUrl) {
			return data.text;
		}

		var template = $('<span class="row">');
		var text = $('<span style="white-space: pre-line;margin: auto 10px">').text(data.text + "\n" + data.typeline);

		var img = $('<img class="mtgcard-thumb">');
		img.attr('src', data.imageUrl);

		template.append(img);
		template.append(text);

		return template[0].outerHTML;
	}

	$(".selectpicker").select2({
		allowClear: true, 
		placeholder: "Select card",
		minimumInputLength: 2,
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
	});

/*
	$('.selectpicker').on('select2:select', function (e) {
		var data = e.params.data;

		var img = $('<img>');
		img.attr('src', data.imageUrl);
		img.appendTo('#inferior_image');

	});*/

	$(".tell_superior").on('click', function(event) {
		event.preventDefault();
		$("#superior").select2('open');
	});
});
</script>
@stop