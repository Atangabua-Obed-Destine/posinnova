$(document).ready(function() {
    if ($('#departments_table').length === 0) {
        return;
    }

    var departments_table = $('#departments_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        ajax: '/departments',
        columnDefs: [
            { targets: 5, orderable: false, searchable: false },
        ],
        columns: [
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'hod_name', name: 'hod_name' },
            { data: 'description', name: 'description' },
            { data: 'is_active', name: 'is_active' },
            { data: 'action', name: 'action' },
        ],
    });

    // Create department
    $(document).on('submit', 'form#department_add_form', function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            method: 'POST',
            url: $(this).attr('action'),
            dataType: 'json',
            data: data,
            beforeSend: function() {
                __disable_submit_button(form.find('button[type="submit"]'));
            },
            success: function(result) {
                if (result.success === true) {
                    $('div.departments_modal').modal('hide');
                    toastr.success(result.msg);
                    departments_table.ajax.reload();
                    var evt = new CustomEvent('departmentAdded', { detail: result.data });
                    window.dispatchEvent(evt);
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    // Edit department
    $(document).on('click', 'button.edit_department_button', function() {
        $('div.departments_modal').load($(this).data('href'), function() {
            $(this).modal('show');

            $('form#department_edit_form').submit(function(e) {
                e.preventDefault();
                var form = $(this);
                var data = form.serialize();

                $.ajax({
                    method: 'POST',
                    url: $(this).attr('action'),
                    dataType: 'json',
                    data: data,
                    beforeSend: function() {
                        __disable_submit_button(form.find('button[type="submit"]'));
                    },
                    success: function(result) {
                        if (result.success === true) {
                            $('div.departments_modal').modal('hide');
                            toastr.success(result.msg);
                            departments_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            });
        });
    });

    // Delete department
    $(document).on('click', 'button.delete_department_button', function() {
        var href = $(this).data('href');
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(function(willDelete) {
            if (willDelete) {
                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success === true) {
                            toastr.success(result.msg);
                            departments_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });
});
