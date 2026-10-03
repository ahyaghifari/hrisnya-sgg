<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Jejak audit dokumen (append-only): document_created/fixed/unfixed/signature_viewed/signed/finalized/revoked. Internal, tidak tampil di verifikasi publik. */
class DocumentEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'event', 'actor_id', 'ip_address', 'user_agent', 'meta'];

    protected $casts = ['meta' => 'array'];
}
