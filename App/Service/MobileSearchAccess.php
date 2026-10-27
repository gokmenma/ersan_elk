<?php

namespace App\Service;

class MobileSearchAccess
{
    public const MODULES = ['personnel', 'invoices', 'vehicles', 'current_accounts', 'documents', 'illegal'];

    public static function modules(): array
    {
        $allowed = [];

        if (Gate::allowsAny(['personel_listesi'])) {
            $allowed['personnel'] = [];
        }

        $invoiceTypes = [];
        if (Gate::allowsAny(['efatura/taslak-list'])) {
            $invoiceTypes[] = 'taslak';
        }
        if (Gate::allowsAny(['efatura/gelen-list'])) {
            $invoiceTypes[] = 'gelen';
        }
        if (Gate::allowsAny(['efatura/giden-list'])) {
            $invoiceTypes[] = 'giden';
        }
        if ($invoiceTypes !== []) {
            $allowed['invoices'] = ['types' => $invoiceTypes];
        }

        if (Gate::allowsAny(['arac_takip'])) {
            $allowed['vehicles'] = [];
        }

        if (Gate::allowsAny(['cari_takibi'])) {
            $allowed['current_accounts'] = [];
        }

        if (Gate::allowsAny(['evrak-takip/list'])) {
            $documentTypes = [];
            if (Gate::allowsAny(['evrak-takip/gelen-evrak'])) {
                $documentTypes[] = 'gelen';
            }
            if (Gate::allowsAny(['evrak-takip/giden-evrak'])) {
                $documentTypes[] = 'giden';
            }
            if ($documentTypes !== []) {
                $allowed['documents'] = ['types' => $documentTypes];
            }
        }

        if (Gate::allowsAny(['kacak_islemleri'])) {
            $allowed['illegal'] = [];
        }

        return $allowed;
    }
}
