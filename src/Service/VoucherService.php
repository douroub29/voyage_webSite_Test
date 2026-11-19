<?php

namespace App\Service;

use App\Entity\Voucher;
use Twig\Environment;

/** Rendu du ticket voucher (HTML imprimable, ou PDF si dompdf est installé). */
class VoucherService
{
    public function __construct(private readonly Environment $twig) {}

    public function isPdfDisponible(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public function renderHtml(Voucher $voucher, bool $pourPdf = false): string
    {
        return $this->twig->render('voucher/ticket.html.twig', [
            'voucher' => $voucher,
            'pourPdf' => $pourPdf,
        ]);
    }

    /** @throws \RuntimeException si dompdf n'est pas installé */
    public function generatePdf(Voucher $voucher): string
    {
        if (!$this->isPdfDisponible()) {
            throw new \RuntimeException('La bibliothèque dompdf n\'est pas installée (composer require dompdf/dompdf).');
        }

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($this->renderHtml($voucher, true));
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
