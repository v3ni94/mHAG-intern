<?php

namespace App\Enums;

enum OrganizationRoleType: string
{
    case ManagingDirector = 'managing_director';
    case BoardMember = 'board_member';
    case AuthorizedOfficer = 'authorized_officer';
    case SupervisoryBoardMember = 'supervisory_board_member';
    case SupervisoryBoardChair = 'supervisory_board_chair';
    case AdvisoryBoard = 'advisory_board';
    case Shareholder = 'shareholder';
    case Stockholder = 'stockholder';
    case ContactPerson = 'contact_person';

    public function label(): string
    {
        return match ($this) {
            self::ManagingDirector => 'Geschäftsführer',
            self::BoardMember => 'Vorstand',
            self::AuthorizedOfficer => 'Prokurist',
            self::SupervisoryBoardMember => 'Aufsichtsrat',
            self::SupervisoryBoardChair => 'Aufsichtsratsvorsitzender',
            self::AdvisoryBoard => 'Beirat',
            self::Shareholder => 'Gesellschafter',
            self::Stockholder => 'Aktionär',
            self::ContactPerson => 'Ansprechpartner',
        };
    }
}
