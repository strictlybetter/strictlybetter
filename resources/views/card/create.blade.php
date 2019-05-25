@extends('layout')

@section('content')
	
	<h1>Add Suggestion</h1>
	{{ Form::open(['route' => 'card.store']) }}

		<div class="row" style="font-size:18px"> 
			<div class="form-group col-md-4">
				<label for="inferior">Inferior Card</label>
				{{ Form::select('inferior', isset($inferiors) ? $inferiors : [], null, ['id' => 'inferior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
			</div>

			<div class="form-group col-md-1"></div>

			<div class="form-group col-md-4">
				<label for="superior">Superior Card</label>
				{{ Form::select('superior', isset($superiors) ? $superiors : [], null, ['id' => 'superior', 'required', 'class' => 'selectpicker form-control input-lg', 'data-live-search' => 'true']) }}
			</div>
		

		<div class="form-group col-md-2" style="margin: auto auto auto 0; padding-top: 10px">
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
/*
	function formatState(state) {
		if (!state.id) {
			return state.text;
		}
		var template = $('<span><img src="'+state.gathererUrl+'" class="img-flag" /> ' + state.text + '</span>');
		return template;
	}*/

	$(".selectpicker").select2({
		allowClear: true, 
		placeholder: "Select card",
		minimumInputLength: 2,
		ajax: {
			url: "{{ route('card.autocomplete') }}",
			dataType: 'json',
			delay: 100,
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
		//templateSelection: formatState
	});
});
</script>
@stop