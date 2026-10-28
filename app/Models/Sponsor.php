<?php

namespace App\Models;

use Database\Factories\SponsorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    /** @use HasFactory<SponsorFactory> */
    use HasFactory;

    protected $table = 'sponsor';

    /** @var list<string> */
    protected $fillable = ['company_name', 'sponsorship_type', 'company_type'];
}
