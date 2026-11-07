<?php

namespace App\Domain\MoneyCommerce\Services;

use App\Domain\Communications\Exceptions\CommunicationProviderException;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use Spatie\Browsershot\Browsershot;

class ReceiptPdfService
{
    public function bytes(SaleReceipt $receipt): string
    {
        $html = view('receipts.sale', ['receipt' => $receipt, 'pdf' => true])->render();
        $browser = Browsershot::html($html)->format('A4')->margins(16, 14, 18, 14)->showBackground()->timeout(15)
            ->showBrowserHeaderAndFooter()->hideHeader()->footerHtml('<div style="font:9px Arial;color:#64748b;text-align:center;width:100%">Receipt · <span class="pageNumber"></span> / <span class="totalPages"></span></div>');
        if ($node = config('receipts.node_binary')) {
            $browser->setNodeBinary($node);
        }
        if ($chrome = config('receipts.chrome_path')) {
            $browser->setChromePath($chrome);
        }

        return $browser->pdf();
    }

    public function filename(SaleReceipt $receipt): string
    {
        return 'receipt-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $receipt->receipt_number).'.pdf';
    }

    public function download(SaleReceipt $receipt)
    {
        return response($this->bytes($receipt), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$this->filename($receipt).'"', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function attachments(CommunicationMessage $message): array
    {
        $link = $message->actionLink;
        $receipt = SaleReceipt::query()->where('business_id', $message->business_id);
        if ($link?->target_type === SaleReceipt::class) {
            $receipt->whereKey($link->target_id);
        } else {
            $receipt->whereIn('sale_id', Sale::query()->select('id')->where('business_id', $message->business_id)->where('appointment_id', $message->intent->source_id));
        }
        $receipt = $receipt->firstOrFail();
        try {
            $bytes = $this->bytes($receipt);
        } catch (\Throwable) {
            throw new CommunicationProviderException('receipt_pdf_unavailable', true);
        }

        return [['filename' => $this->filename($receipt), 'content' => $bytes, 'content_type' => 'application/pdf']];
    }
}
