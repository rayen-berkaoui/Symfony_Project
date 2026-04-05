<?php

namespace App\Exception;

final class ActivityMigrationNeededException extends \RuntimeException
{
    public static function forUniqueActivity(): self
    {
        $lri = "\u{2066}";
        $pdi = "\u{2069}";

        $msg = "تحديث قاعدة البيانات مطلوب.\n\n"
            ."الفهرس unique_activity القديم لا يتضمن comment_id، لذلك لا يمكن أن يكون لديك نفس النوع (مثل DISLIKE) على المنشور وعلى تعليق في آن واحد.\n\n"
            ."الحل — في مجلد المشروع نفّذ:\n"
            .$lri.'php bin/console doctrine:migrations:migrate'.$pdi."\n\n"
            .'أو من phpMyAdmin: احذف الفهرس القديم ثم أنشئ فهرساً يتضمن الأعمدة post_id, user_key, activity_type, comment_id (انظر الملف '
            .$lri.'migrations/Version20260403183000.php'.$pdi.').';

        return new self($msg);
    }
}
