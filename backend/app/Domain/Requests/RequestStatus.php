<?php
namespace App\Domain\Requests;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}