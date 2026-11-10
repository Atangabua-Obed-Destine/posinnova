{{-- Export buttons for server-side rich exports.
     Required variables:
       $export_base     - e.g. '/reports/export/sale-report'
       $export_map_id   - unique camelCase id for the window.__exportMap_{id} JS object
--}}
<div class="tw-flex tw-gap-2 tw-justify-end tw-mb-2 no-print">
    <a href="#" class="btn btn-success btn-sm btn-report-export"
        data-export-base="{{ url($export_base) }}"
        data-export-format="excel"
        data-export-map="{{ $export_map_id }}">
        <i class="fa fa-file-excel"></i> @lang('report.export_excel')
    </a>
    <a href="#" class="btn btn-danger btn-sm btn-report-export"
        data-export-base="{{ url($export_base) }}"
        data-export-format="pdf"
        data-export-map="{{ $export_map_id }}">
        <i class="fa fa-file-pdf"></i> @lang('report.export_pdf')
    </a>
</div>
