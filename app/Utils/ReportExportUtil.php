<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;

class ReportExportUtil
{
    public static function getBusinessDetails(int $business_id): array
    {
        $business = Business::leftjoin('currencies AS cur', 'business.currency_id', 'cur.id')
            ->select(
                'business.name',
                'business.logo',
                'business.tax_number_1',
                'business.tax_label_1',
                'cur.symbol as currency_symbol',
                'cur.code as currency_code'
            )
            ->where('business.id', $business_id)
            ->first();

        $details = $business ? $business->toArray() : [];

        // Address & contact info live on business_locations, not business
        $location = BusinessLocation::where('business_id', $business_id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if ($location) {
            $details['landmark'] = $location->landmark;
            $details['city']     = $location->city;
            $details['state']    = $location->state;
            $details['country']  = $location->country;
            $details['zip_code'] = $location->zip_code;
            $details['mobile']   = $location->mobile;
            $details['email']    = $location->email;
            $details['website']  = $location->website;
        }

        return $details;
    }

    public static function buildFilterSummary(array $filters): string
    {
        $parts = [];

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $parts[] = __('messages.date') . ': ' . $filters['start_date'] . ' ~ ' . $filters['end_date'];
        }

        if (!empty($filters['location_name'])) {
            $parts[] = __('purchase.business_location') . ': ' . $filters['location_name'];
        }

        if (!empty($filters['customer_name'])) {
            $parts[] = __('contact.customer') . ': ' . $filters['customer_name'];
        }

        if (!empty($filters['supplier_name'])) {
            $parts[] = __('contact.supplier') . ': ' . $filters['supplier_name'];
        }

        if (!empty($filters['category_name'])) {
            $parts[] = __('product.category') . ': ' . $filters['category_name'];
        }

        if (!empty($filters['brand_name'])) {
            $parts[] = __('product.brand') . ': ' . $filters['brand_name'];
        }

        if (!empty($filters['payment_status'])) {
            $parts[] = __('lang_v1.payment_status') . ': ' . ucfirst($filters['payment_status']);
        }

        if (!empty($filters['status'])) {
            $parts[] = __('messages.status') . ': ' . ucfirst($filters['status']);
        }

        return empty($parts) ? __('report.all_data') : implode('  |  ', $parts);
    }

    public static function buildHeaderRows(array $business, string $report_title, string $filter_summary, string $generated_by): array
    {
        $rows = [];
        $rows[] = [$business['name'] ?? 'PosInnova'];
        $rows[] = [$report_title];
        $rows[] = [__('report.generated_on') . ': ' . now()->format('d/m/Y H:i') . '   ' . __('messages.by') . ': ' . $generated_by];
        $rows[] = [__('report.filters') . ': ' . $filter_summary];
        $rows[] = [''];  // blank spacer
        return $rows;
    }

    // PDF-specific: returns the business logo public path or null
    public static function getLogoPath(array $business): ?string
    {
        if (!empty($business['logo'])) {
            $path = public_path('uploads/business_logos/' . $business['logo']);
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function formatCurrency(float $amount, string $symbol = ''): string
    {
        return $symbol . number_format($amount, 2, '.', ',');
    }

    public static function buildAddressLine(array $business): string
    {
        $parts = array_filter([
            $business['landmark'] ?? '',
            $business['city'] ?? '',
            $business['state'] ?? '',
            $business['country'] ?? '',
        ]);
        return implode(', ', $parts);
    }

    public static function buildContactLine(array $business): string
    {
        $parts = [];
        if (!empty($business['mobile'])) $parts[] = __('lang_v1.mobile') . ': ' . $business['mobile'];
        if (!empty($business['email'])) $parts[] = 'Email: ' . $business['email'];
        if (!empty($business['website'])) $parts[] = $business['website'];
        if (!empty($business['tax_number_1'])) $parts[] = ($business['tax_label_1'] ?? 'Tax No.') . ': ' . $business['tax_number_1'];
        return implode('  |  ', $parts);
    }
}
