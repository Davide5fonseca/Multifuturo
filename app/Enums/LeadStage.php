<?php

namespace App\Enums;

/**
 * Estados do acompanhamento de uma lead (pipeline). Os primeiros aplicam-se a
 * compradores; os de angariação seguem outro caminho — ver forKind().
 */
enum LeadStage: string
{
    // Comprador
    case Received = 'received';
    case Qualification = 'qualification';
    case Visit = 'visit';
    case Proposal = 'proposal';
    case Closed = 'closed';
    // Angariação
    case Prospecting = 'prospecting';
    case ContactOwner = 'contact_owner';
    case Valuation = 'valuation';
    case Listed = 'listed';
    // Comum
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::Qualification => 'Qualificação',
            self::Visit => 'Visita',
            self::Proposal => 'Proposta',
            self::Closed => 'Fechado',
            self::Prospecting => 'Prospeção',
            self::ContactOwner => 'Contactar proprietário',
            self::Valuation => 'Avaliação',
            self::Listed => 'Angariado',
            self::Lost => 'Perdido',
        };
    }

    /** @return array<string, string> estados disponíveis para o tipo de lead */
    public static function forKind(LeadKind $kind): array
    {
        $cases = $kind === LeadKind::Listing
            ? [self::Prospecting, self::ContactOwner, self::Valuation, self::Listed, self::Lost]
            : [self::Received, self::Qualification, self::Visit, self::Proposal, self::Closed, self::Lost];

        return collect($cases)->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> todos, para filtros */
    public static function all(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
