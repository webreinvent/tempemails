<?php

namespace Modules\TempEmails\Entities;

use DOMDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeMail extends Model
{
    use SoftDeletes;

    //-------------------------------------------------
    protected $table = 'te_mails';
    //-------------------------------------------------
    protected $dates = [
        'created_at', 'updated_at', 'deleted_at'
    ];
    //-------------------------------------------------
    protected $dateFormat = 'Y-m-d H:i:s';
    //-------------------------------------------------

    protected $fillable = [
        'te_account_id', 'received_at', 'received_at', 'uid', 'imap_msgno', 'subject',
        'message', 'message_text', 'message_raw',
        'meta', 'created_by', 'updated_by', 'deleted_by',
        'created_at', 'updated_at', 'deleted_at',
    ];

    //-------------------------------------------------
    protected $appends  = [
        'iframe', 'formatted_text'
    ];
    //-------------------------------------------------

    public function getIframeAttribute()
    {
        $encrypted_id = \Crypt::encrypt($this->id);
        $iframe = \URL::route('te.email.iframe', [$encrypted_id]);
        return $this->attributes['iframe']=$iframe;
    }

    //-------------------------------------------------
    public function getFormattedTextAttribute()
    {
        return $this->attributes['formatted_text']=nl2br($this->message_text);
    }
    //-------------------------------------------------
    public function formattedHtml()
    {

        if($this->message)
        {
            $dom = new DOMDocument();
            // we want nice output
            $dom->preserveWhiteSpace = false;
            $dom->loadHTML($this->message);
            $dom->formatOutput = true;
            $format = new \Format();
            $fomatted = $format->HTML($this->message);
        } else
        {
            $fomatted = '<p style="background-color: #fff6e5; font-family: Arial;
                            padding: 10px 10px; color: #ba7900;
                            font-size: 12px;">Email format is "text/plain", please check "Text" tab</p>';
        }


        return $fomatted;
    }
    //-------------------------------------------------
    public function scopeCreatedBy($query, $user_id)
    {
        return $query->where('created_by', $user_id);
    }
    //-------------------------------------------------
    public function scopeUpdatedBy($query, $user_id)
    {
        return $query->where('updated_by', $user_id);
    }
    //-------------------------------------------------
    public function scopeDeletedBy($query, $user_id)
    {
        return $query->where('deleted_by', $user_id);
    }
    //-------------------------------------------------
    public function scopeCreatedBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', array($from, $to));
    }
    //-------------------------------------------------
    public function scopeUpdatedBetween($query, $from, $to)
    {
        return $query->whereBetween('updated_at', array($from, $to));
    }
    //-------------------------------------------------
    public function scopeDeletedBetween($query, $from, $to)
    {
        return $query->whereBetween('deleted_at', array($from, $to));
    }
    //-------------------------------------------------
    public function createdBy() {
        return $this->belongsTo( 'Modules\Core\Entities\User',
                                 'created_by', 'id'
        );
    }
    //-------------------------------------------------
    public function updatedBy() {
        return $this->belongsTo( 'Modules\Core\Entities\User',
                                 'updated_by', 'id'
        );
    }
    //-------------------------------------------------
    public function deletedBy() {
        return $this->belongsTo( 'Modules\Core\Entities\User',
                                 'deleted_by', 'id'
        );
    }
    //-------------------------------------------------
    public function account() {
        return $this->belongsTo( 'Modules\TempEmails\Entities\TeAccount',
                                 'te_account_id', 'id'
        );
    }
    //-------------------------------------------------
    public function contacts() {
        return $this->hasMany( 'Modules\TempEmails\Entities\TeContact',
                                     'te_mail_id', 'id');
    }
    //-------------------------------------------------
    public function from()
    {
        return $this->contacts()->where('type', 'from');
    }
    //-------------------------------------------------

    //-------------------------------------------------
    public function to()
    {
        return $this->contacts()->where('type', 'to');
    }
    //-------------------------------------------------
    public function cc()
    {
        return $this->contacts()->where('type', 'cc');
    }
    //-------------------------------------------------
    public function attachments() {
        return $this->hasMany( 'Modules\TempEmails\Entities\TeMailAttachment',
                               'te_mail_id', 'id');
    }
    //-------------------------------------------------
/*    protected static function boot() {
        parent::boot();

        static::deleting(function($check) {
            $check->contacts()->delete();
            $check->attachments()->delete();
        });
    }*/
    //-------------------------------------------------
    public static function deleteMail($id)
    {
        $mail = TeMail::withTrashed()->where('id', $id)->first();


        if(!$mail)
        {
            $response['status'] = 'failed';
            $response['errors'][]= 'Mail not exist';
            return $response;
        }


        //delete attachments
        $attachments = TeMailAttachment::where('te_mail_id', $mail->id)->withTrashed()->get();

        if($attachments)
        {
            $base_url = \URL::to("/");
            foreach ($attachments as $attachment)
            {
                $file_path = str_replace($base_url."/", "", $attachment->url);
                \File::delete($file_path);
                $attachment->forceDelete();
            }
        }

        //delete contacts
        $contacts = TeContact::where('te_mail_id', $mail->id)->withTrashed()->get();

        if($contacts)
        {
            foreach ($contacts as $contact)
            {
                $contact->forceDelete();
            }
        }

        //delete mails
        $mail->forceDelete();

        //delete mail from server


    }
    //-------------------------------------------------
    public static function imapDeleteMail($uids_array)
    {
        $inbox_config['hostname']= env('IMAP_HOST');
        $inbox_config['email']= env('IMAP_EMAIL');
        $inbox_config['password']= env('IMAP_PASSWORD');
        $inbox_config['upload_path']= 'files/attachments';

        $mailbox = new \PhpImap\Mailbox($inbox_config['hostname'], $inbox_config['email'],
            $inbox_config['password'], $inbox_config['upload_path']);


        $result['data'] = [];


        if(is_array($uids_array) && count($uids_array) > 0)
        {
            $i = 0;
            foreach ($uids_array as $uid)
            {
                $mailbox->deleteMail($uid);
                $result['data'][$i] = $uid." is deleted from server";
                $i++;
            }
        }

        return $result;

    }
    //-------------------------------------------------
    //-------------------------------------------------
    //-------------------------------------------------
    //-------------------------------------------------
}
