<script>
$('#button-tbai-resend').on('click',function(e){
	e.preventDefault();

	var button = $(this);

	button.prop('disabled', true);
	button.find('svg.bi').addClass('bi-spin').find('use').attr('href', 'view/image/bootstrap-icons.svg#arrow-repeat');

	$.ajax({
		url:'index.php?route=ticketbai/ticketbai/invoiceResend&token=<?php echo $token; ?>&invoice_id=<?php echo $invoice_id; ?>',
		type:'get',
		dataType:'json',
		success:function(json){
			if(json['error']){
				alertMessage('danger',json['error']);
			} else {
				$('#tbai-sent-date').text(json['sent_date']);
				$('#tbai-status').html(json['status']);
				$('#tbai-notice').html(json['notice']);
				$('#tbai-identifier').text(json['identifier']);

				alertMessage(json['success'] ? 'success' : 'danger', json['message']);
			}
		},
		error:function(request){
			console.log("ajax call went wrong:" + request.responseText);
		},
		complete:function(){
			button.prop('disabled', false);
			button.find('svg.bi').removeClass('bi-spin').find('use').attr('href', 'view/image/bootstrap-icons.svg#send');
		}
	});
});
</script>
