<?php

set_time_limit(60);
ini_set('memory_limit', '2048M');
require_once "../comunes/top.inc.php";
require_once("../comunes/classes/class.mymongodb.php");
require_once "../comunes/classes/class.coTabulaMongo.php";
if (!isset($_REQUEST["act"])) {
    exit;
}

$act = expect_pure_alphanumeric($_REQUEST["act"]);
$json = array();
$limpiar = array();

global $lang, $Central;
switch ($act) {
    case "enviar":
        $mongoCNT = new MYMONGODB('CNT');
        $d = jsonStart();
        if (trim($d['tel'] != '')) {
            $c = $mongoCNT->guardar('cbEnvioIVR', [
                "civr_ivrId" => time() . substr(microtime(), 2, 8),
                "civr_clienteId" => 1756944276, //cedula
                "civr_ramaId" => 2250,
                "civr_prefijo" => "18#67*30*21#",
                "civr_area" => substr($d['tel'], 0, 2),
                "civr_telefono" => substr($d['tel'], 2, strlen($d['tel'])),
                "civr_archivoAudioIvr" => "a4f/a4f778e39808a06540812da4d7505vvc3_fijo",
                "civr_estadoAudioIvr" => "listo",
                "civr_estado" => 0,
                "civr_fechaEjecucionLlamada" => 1556307785,
                "civr_duracionLlamada" => 0,
                "civr_llamadaId" => 0,
                "civr_fechaPeriodo" => time(),
                "civr_fechaCreacion" => time()]);
            trigger_error('IVR ' . $c);
        }
        if (trim($d['email']) != '' && strpos($d['email'], '@') != false) {
            require_once("../alerts/classes/class.alEvent.php");
            $alEv = new alEvent();
            $img1 = '<img src="data:image/jpeg;base64, /9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAoHBwkHBgoJCAkLCwoMDxkQDw4ODx4WFxIZJCAmJSMg
IyIoLTkwKCo2KyIjMkQyNjs9QEBAJjBGS0U+Sjk/QD3/2wBDAQsLCw8NDx0QEB09KSMpPT09PT09
PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT3/wAARCACFAW0DASIA
AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA
AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3
ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm
p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEA
AwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSEx
BhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElK
U1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3
uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD2WijG
Rj1rF/4RSw/v3X/f40nfoXBQfxO3yubVFYv/AAilh/fuv+/xo/4RSw/v3X/f40rvsaclH+Z/d/wT
aorF/wCETsD/AB3X/f8ANbEUYiiSNc7UUKMnJ4pq/Uiagvhd/lb9R1FLRTMxKKWigBKKWigBKKWi
gBKKWigBKKWigBKKWigBKKWigBKKWigBKKWigBKKWigBKKWigBKKWigBKKWigBKKWkZgilmIVQMk
k8AUAFFY0XiizuLpUgDPAZPK88EBd+MgAdSPcVak12xjvWtDKWnUZZVQnb9cCldD5WX6KpW+t6fd
TCKG7jaQ8Bc4J/Or1MGmhKKWigQlFLRQAlBpaQ0AFLSUtAGRrXiaw0CWGO+M26YEoI4y2cfT60aV
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
AH//2Q==" alt="LINK" />';
            $img2 = '<img src="data:image/jpeg;base64, /9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAoHBwkHBgoJCAkLCwoMDxkQDw4ODx4WFxIZJCAmJSMg
IyIoLTkwKCo2KyIjMkQyNjs9QEBAJjBGS0U+Sjk/QD3/2wBDAQsLCw8NDx0QEB09KSMpPT09PT09
PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT3/wAARCAB4AWoDASIA
AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA
AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3
ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm
p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEA
AwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSEx
BhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElK
U1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3
uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD1+8u4
7G0e4lDFEAyFGT1x0rJ/4S7T/wDnndf9+TW5S0nfoawlTS96N/nb9DC/4S7T/wDnndf9+TR/wl2n
/wDPO6/78mt2ilZ9yuej/I/v/wCAYX/CXaf/AM87r/vya1rS5jvbWO4iDBJBkBhg/lU9FNX6kTlT
a92Nvnf9BKKWimZiUUtFACUUtFACUUtFACUUtFACUUtFACUUtFACUUtFACUUtFACUUtFACUUtFAC
UUtFACUUtFACUUtFACUUtFACVTuNY02zl8q61C0hk/uSTKp/ImuD+LHjuXQLZdK0uUx3s67pZVPz
RIegHox557AfSvCGkklkLv8AMzHJLck/Unk0AfWi6vpz/cv7RvpMp/rUi31o33bmA/SQV8t2KGNQ
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" alt="LINK" />';
            $img3 = '<img src="data:image/jpeg;base64, /9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAoHBwkHBgoJCAkLCwoMDxkQDw4ODx4WFxIZJCAmJSMg
IyIoLTkwKCo2KyIjMkQyNjs9QEBAJjBGS0U+Sjk/QD3/2wBDAQsLCw8NDx0QEB09KSMpPT09PT09
PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT3/wAARCACFATMDASIA
AhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQA
AAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3
ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWm
p6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEA
AwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSEx
BhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElK
U1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3
uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD1+7u4
bG2ae4YrGuMkAnrx2rO/4SnSv+e7/wDfpv8ACtejA9KTuaRcEveTfz/4DMj/AISnSv8Anu//AH6b
/Cj/AISnSv8Anu//AH6b/CtfA9KMD0o1K5qX8r+//gFSw1S11MSfZXL+XjdlCvX6/SrdFLTMpWv7
uwlFLRQISilooASilooASilooASilooASilooASilooASilooASilooASilooASilooASilooAa7
LGpZ2CqOpJwBVf8AtOx/5/Lb/v6v+NecJBN8S9a1Ce8nuE0TTpTDDaQvsM74zkntxj8+1aKfDvRJ
1miGn3tq3l/u5WvGYbsccZ7UAdt/adj/AM/lt/39X/Gj+07H/n8tv+/q/wCNeAN4I8Xw3IR9LvZI
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
dFVBhRgUUUAKTRRRQB//2Q==" alt="LINK" />';
            $htmlEvent = '<p>Hola.!

Conoce los beneficios que puedes tener si utilizas nuestra APP.

Descárgala en tu smartphone de forma gratuita y gana premios  </p><br/>';

            $howmany = $alEv->send_alert(array(
                "name" => "Enviar mail de error",
                "explanation" => "Mail de error",
                "subject" => 'Notificacion de actas',
                "to" => array($d['email'], 'vecellc@hotmail.com', 'gsaltos@me.com'),
                "html" => $htmlEvent . $img1 . $img2 . $img3 . '<br/><hr> Vitality Ecuador</hr><br/>',
                "family" => "Carga Bienvenida",
                "from" => 'info@espaciolink.com',
                "attachments" => '',
                "identifier" => 0
            ));
        }
        $json['resp'] = date('Y-m-d', strtotime('+ 7 day', time())) . ',' . date('Y-m-d', strtotime('+ 14 day', time()));
        break;
    case "procesaGestion":
        $d = jsonStart();
        $mongo = new MYMONGODB();
        $mongoCNT = new MYMONGODB('CNT');
        if (trim($d['tel1'] != '')) {
            $c = $mongoCNT->guardar('cbEnvioIVR', [
                "civr_ivrId" => time() . substr(microtime(), 2, 8),
                "civr_clienteId" => 1756944276, //cedula
                "civr_ramaId" => 2250,
                "civr_prefijo" => "18#67*30*21#",
                "civr_area" => substr($d['tel1'], 0, 2),
                "civr_telefono" => substr($d['tel1'], 2, strlen($d['tel1'])),
                "civr_archivoAudioIvr" => "a4f/a4f778e39808a06540812da4d7504cc3_fijo",
                "civr_estadoAudioIvr" => "listo",
                "civr_estado" => 0,
                "civr_fechaEjecucionLlamada" => 1556307785,
                "civr_duracionLlamada" => 0,
                "civr_llamadaId" => 0,
                "civr_fechaPeriodo" => time(),
                "civr_fechaCreacion" => time()]);

            if (trim($d['tel2'] != '')) {
                $c = $mongoCNT->guardar('cbEnvioIVR', [
                    "civr_ivrId" => time() . substr(microtime(), 2, 8),
                    "civr_clienteId" => 1756944276, //cedula
                    "civr_ramaId" => 2250,
                    "civr_prefijo" => "18#67*30*21#",
                    "civr_area" => substr($d['tel2'], 0, 2),
                    "civr_telefono" => substr($d['tel2'], 2, strlen($d['tel2'])),
                    "civr_archivoAudioIvr" => "a4f/a4f778e39808a06540812da4d7504cc3_fijo",
                    "civr_estadoAudioIvr" => "listo",
                    "civr_estado" => 0,
                    "civr_fechaEjecucionLlamada" => 1556307785,
                    "civr_duracionLlamada" => 0,
                    "civr_llamadaId" => 0,
                    "civr_fechaPeriodo" => time(),
                    "civr_fechaCreacion" => time()]);
            }
            trigger_error('IVR ' . $c);
        }
        if (trim($d['email1']) != '' && strpos($d['email1'], '@') != false) {
            require_once("../alerts/classes/class.alEvent.php");
            $alEv = new alEvent();
            $imgLogo = '<img src="data:image/jpeg;base64, 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" alt="LINK" />';
            $htmlEvent = '<hr> Estimado Sr. /Sra.:</hr><br/>'
                    . '<p>Esto es una prueba de una Acta de mediación </p><br/>';
//                    . '<p>' . $imgLogo . '</p>';

            $howmany = $alEv->send_alert(array(
                "name" => "Enviar mail de error",
                "explanation" => "Mail de error",
                "subject" => 'Notificacion de actas',
                "to" => array($d['email1']),
                "html" => $htmlEvent . '<br/><p>' . $imgLogo . '</p><br/>' . $d['content'],
                "family" => "Carga Bienvenida",
                "from" => 'info@espaciolink.com',
                "attachments" => '',
                "identifier" => 0
            ));
            if (trim($d['email2']) != '' && strpos($d['email2'], '@') != false) {
                $howmany = $alEv->send_alert(array(
                    "name" => "Enviar mail de error",
                    "explanation" => "Mail de error",
                    "subject" => 'Notificacion de actas',
                    "to" => array($d['email2']),
                    "html" => $htmlEvent . '<br/><p>' . $imgLogo . '</p><br/>' . $d['content'],
                    "family" => "Carga Bienvenida",
                    "from" => 'info@espaciolink.com',
                    "attachments" => '',
                    "identifier" => 0
                ));
                trigger_error('email2 ' . $d['email2'] . ' - ' . $howmany);
            }
        }
        break;
    case "eventos":
        $limpiar = [];
        $d = jsonStart();
        $json = '';
        if (expect_pure_alphanumeric($_REQUEST["ci"]) != '') {
            $ngTabula = new coTabulaMongo();
            $ngTabula->setInput($d);
            $ngTabula->setLimpiador($limpiar);
            $ngTabula->setQueryDatos(
                    'eventosDemo'
                    , ['cedula' => (string) expect_pure_alphanumeric($_REQUEST["ci"])]
                    , []
                    , ["_id" => -1]
            );
            $json = $ngTabula->responde();
        }
        break;
    case "registraEvento":
        $mongo = new MYMONGODB();
        $d = jsonStart();
        if (trim($d['dato']) != '') {
            if ($d['tipo'] == 'email') {
                $tipo = 'MAIL-ENVIADO';
            }
            if ($d['tipo'] == 'tel') {
                $tipo = 'SMS-ENVIADO';
                $mongo->guardar('eventosDemo', ['origen' => 'sistema', 'tipo' => 'IVR-ENVIADO', 'cedula' => $d['ci'], 'marcado' => $d['dato'], 'fecha' => time()]);

                $mongoCNT = new MYMONGODB('CNT');
                $c = $mongoCNT->guardar('cbEnvioIVR', [
                    "civr_ivrId" => time() . substr(microtime(), 2, 8),
                    "civr_clienteId" => 1756944276, //cedula
                    "civr_ramaId" => 2250,
                    "civr_prefijo" => "18#67*30*21#",
                    "civr_area" => substr($d['dato'], 0, 2),
                    "civr_telefono" => substr($d['dato'], 2, strlen($d['dato'])),
                    "civr_archivoAudioIvr" => "a4f/a4f778e39808a06540812da4d7504db5_fijo",
                    "civr_estadoAudioIvr" => "listo",
                    "civr_estado" => 0,
                    "civr_fechaEjecucionLlamada" => 1556307785,
                    "civr_duracionLlamada" => 0,
                    "civr_llamadaId" => 0,
                    "civr_fechaPeriodo" => time(),
                    "civr_fechaCreacion" => time()]);
                trigger_error('IVR ' . $c);
            }
            $mongo->guardar('eventosDemo', ['origen' => 'sistema', 'tipo' => $tipo, 'cedula' => $d['ci'], 'marcado' => $d['dato'], 'fecha' => time()]);
            trigger_error($tipo . ' - ' . $d['dato']);
            if ($tipo == 'MAIL-ENVIADO') {
                require_once("../alerts/classes/class.alEvent.php");
                $alEv = new alEvent();
                $imgLogo = '<img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAgAAZABkAAD/7AARRHVja3kAAQAEAAAAPAAA/+4ADkFkb2JlAGTAAAAAAf/bAIQABgQEBAUEBgUFBgkGBQYJCwgGBggLDAoKCwoKDBAMDAwMDAwQDA4PEA8ODBMTFBQTExwbGxscHx8fHx8fHx8fHwEHBwcNDA0YEBAYGhURFRofHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8f/8AAEQgAOQCFAwERAAIRAQMRAf/EAKwAAAIDAQEBAQAAAAAAAAAAAAAHBAYIBQMBAgEAAgIDAQAAAAAAAAAAAAAAAAUEBgIDBwEQAAEDAwIDBAQICgsAAAAAAAECAwQAEQUGByExEkFREwhhcbN0sSIysnMUFTaBoVJyIzODFhc3kUJigpLCk1RFdTgRAAIBAgMFBAgFBQEAAAAAAAABAgMEESEFMXESMjNBgXIGUWGxwSJCEzSR0YIjFPChUmI1JP/aAAwDAQACEQMRAD8A0xqLLDEYWXkSOr6ugEA8rqUEj8ZrXWqcEWyRaUPq1FD0iWz+80+OFLjyFh4cUgWtf1WtSh3U28mXG20CD5lkM/bzWLmo9ERs/LQG3el0SAjkSyopUoD09N6a0qjcMWVfUbNUbh04bMsO8Wuot8iErdhyChYJLbaLEDuvfnS2d3OTyyLJbeXkudZDI2u1q5rDSbOVebDclLi2Hwn5JU2flD1g0zoTco4vaVvVLSNCs4ReMewttbRcFABQAUAFACq1luiuBkZcNtZaRGWWwE8CSnmSaVXF3LiaRaNP0ZTgpPPEi7UbrZLUGqpGClfpWDHXIjvKt1pLakgg94PVW60rSk8JHut6TToUlOOUscGN+p5VgoAKACgAoAq26BtoPL/Ro9qio9302M9G+6hv9zMn5g8Veqk0Dpy5DSHl9SlW1sBKhdKnZIIPaC8qnVvyHNdbf/ql3ewomq/Ltg3s445C1O1jYDzhUqG8ApbfUblKFdSRbuvUedGCe1IZ0darShnCUn6UOjRWlMRpXTcTC4klcRgFXjKIUp1azdTiiOF1GpkIqKwRXbmvKrNyltOo7kceyvodkttrvbpUsA3/AAmhziu01xpTaxSZI6hbquLc79lZmsjOZLHtmy5LaT3FQrB1IrtNiozexM9WZUZ8XZdQ5+YoH4KyUk9hjKEo7VgelemJlXcwn968v7y58NIavUe86ho/Qh4USfL1/NBXuEj5zdTLPmFXmfo/qXvNQ0yKIeb8mPHT1PupbT3qIHw145JbTKMHLYsT6y+y+2HGXEuNnktBCh/SKE09gSi4vB5H7r0xCgCrbofcLL/Ro9qio9302M9G+6hv9zMn5jmv1UmgdO+Qfu0c12DsYqW0bOMJmrSe4hxfGmsZNUW16zn1/TU79Rfa4iu1BrrIuIU2k8xzNKVjLaXC306Ec2NHZPP5B/a7JSXnVOLgLk+ApRuUhLfWAPQDTag2qT9RU9apRleRSXNw4/jgLbLa6nOLCFKJSflE0oxci1UNNiliS8hvlNYxcbGRwp1xpPhoSLkqPZe3E+ipCqVJRUSBLRqMJuc3tK3L3E1qyUvTociMw4firdZcQk3/ALSgK8lSl2m+lC1k8F7SfA3FyIKXkuKadTxS4gkEH1itXxJ5Mly0unJelDv2j3GOrcfJjTLDKY8p8Ujh4ja79Llu/hY03taznHPaila5patZpx5Jf2foEpuZ968v7y58NK6vUe8uuj9CHhRK8vQP8T1Hs+oP/ObqZZ8wq8z9H9S95o7O56HiIpdeUC4R8Ru/P0n0VMrVlBZlMtraVWWC2CB17uw/47oac6l8QT2D0UonUlUZdrHSYU44y2Hd8t8rVmSl5bKyw4MA8kIYU5cIckJVxLYP5KbgkVPs4OOPoFHmKtSfDGKXEvYPSpxVwoAq26H3Cy/0aPaoqPd9NjPRvuob/czJ+Y5r9VJoHTvkHptr/wCfZP0U72iqaLovcyh3X/SjviI/Lcz6qVwL7DlHNsf/ACi1B+dL9hTSl0n3lE1X72G+PtE5lTZV6VQL3S2DO8sul8dLk5fUctlL0yK4iLCKwFeHdPWtSb8lHgL01tILaUfzHcS4lBPJ5j8yGPhZCG9CmsokRX0lDrLgCkqSRbkamNYlXhNxaaeDRjvPYROIzORgN38GLIdaavz6ErIT+KkdTKTR1uxq8dKMu1xTLr5cX3E7gzWgbIcx7nUO/pdbIqZZ8xW/NGdJeL8zl7l8dV5f3pz4ahVuo9440foQ8KPu0+Xj6e1A/l3R1LMZbDSSbDqWpJufQOmsqdf6bxNetWn16agv8sSZrvcCVkFuBp0qWvmq9a5Tc3izXp2mRprFojbTbPua2mqzWbeAwEZ0oMVC/wBK+4niUrtxQjv7T2VPt6Cln2C3XNVlSfBHm9hqGHDiQorUSIyhiKwkIZZbAShKRwAAFMUsCkyk5PF7T2oPAoAq26H3Cy/0aPaoqPd9NjPRvuob/czJ+Y5r9VJoHTvkHptr/wCfZP0U72iqaLovcyh3X/SjviI/Lcz6qVwL7DlHNsf/ACi1B+dL9hTSl0n3lE1X72G+PtE5luZpVAvVPlHT5Xvu5m/fk+yFN7TlZQPMnWjuHTUsrpk/cQD95sv707840gqdR7zqek9CHhR1fLn/ADGlf9e77Rup1nzCXzP0l4vzOfuX968v7058NQq3Ue8caP0IeFFISrJSprOOxjDkqdIUEMx2QVLUT3AVlCnxEq7u40liydqDT2o9MZNGN1BG+ryHW0utqCgtCkqHHpWOB6TwPprKpScdpHsNShcLGLLPtxrWVpTKiXHJchv2TOiX4LR+UO5Sew15SrunL1GOq6bG6p4PKS2M1FictAy2PZyEB0PRX09SFj8YI7CO0U5hNSWKOZ16E6U3CawkiXWRqCgCq7prSjQGYUo2SG0XP7VFR7rpsaaKsbuG/wBzMm5V1tQWpJBFqTwR055QzHrtutI8vcpZPxQ1OJP7RVNF0X3lCuM9Sj4oiOybza+opIItSqCL6lhHMc+x60/wg1Cq/AKmX/0KaUuk+8omqZ31PfH2iayTza7lKgRalcEXuKwWY7PK6oHTmbt/vk+yFNrTlZz/AMydaO4dVSyumTNw32jqnLpCh1CW7cf3jSGovje86rpUWqEPCjr+XJSTuPKAP/Hu+0bqbZ8wi8zv9peL8zmblPtHVuYQFDqEpwEfhqHWX7j3jrSItW8H/qiX5eilW6ZPAkY+Rb/E3Uuz5hP5mf7X6kPvcXQON1pgVwJFmpjV3IEy11NO2+arkoVOq01NYFSsL2VvU4ls7UZOmQsngstIxOUaLE6IvocQeR7lJPalQ4g0nqU2ngzplndwrQTTyZfdsd0XNM5dmFJUXMPOcSh9q/6taj0h1H+bvFbbWq4PDsFmt6XCvT4llUjs9fqNKv5GCwtKHpDbalWKQpQHA8qauaW1nPY0pSWKRIuLXvw76yNZzdSYGLn8FNw0tSkMTWy2paPlJPNKhftSoA1jOKksGb7a4lRqKcdsTOcryx67XkFR28rFOPKrCWQoL6O/w+/0dVQv4jxLQ/McXHNPEe2nNv8AE4XQqdIIWt6GWHGJDyuC1qev4i/QSVXHdUtU0o8JXKl7OVb6vzY4/gIvK+WbXP2gtqBk4r2PKv0b7nUhYQfykC/H1Gon8R4ljXmSLjmniOzQG3GP0nos6bU8ZgkBwz5BHR4i3h0r6RxsLcBUuFNRjgV26vZVav1NmGzuEvnPLPrQZJxGJycd/HKUfCceuhxKSeAUkXBI9FRHaPHIsUfMicfiTxHNtXt0xoTTf2YJJmS33C/Nk26QpwgCyU3NkpAtUulT4VgV2/vHcT4nki5VsIQhtzPL/n8tqGTmNNzmktzll1+JIunocPyilYvcE8eVQ6lri8UWex1/6dNQn8pYdl9l5OiZUrMZeYmXl5TXgJbaB8NpsqClfGPFSlFIrZRo8GZA1PVP5GEUvhRxd0tgsxnM8/mtNzmmlTFdcmFIukBy1ipCx2K7iKwq22LxRMsNedKmoT+XYzsbNbJv6Lmv5vLzEy8w+0WG22gQ0y2ohSuJ4qUrpFZ0aPDmQ9T1T+QuFL4RtVIE4vt19pIGuIjb7DwgZyKLR5nT1BaOfhugWJTfkeytNWip7xnp2pztnhti+wWujfLRnW88xM1PkGjj4jiXRGjXUp4oNwlSjbpT31pha4PMaXev8UMIJ4saGq9FZ3I5sSYLzZiv9PiB1RHhG3Qq6B+sT0cUpuLKrZOjjLEX2uoRp0+FrNf13Ft+yx9h/ZXjLt9V+q/WP6/6vw+v19tbuHLAW/V/c4/XiTqyNQUAFABQAUAFABQAUAFABQAUAFABQAUAFABQAUAf/9k=" alt="LINK" />';
                $htmlEvent = '<hr> Estimado Sr. /Sra.:</hr><br/>'
                        . '<p>Esto es una prueba de planes y tarifas para AI6 Saturdays </p><br/>'
                        . '<p>' . $imgLogo . '</p>';
                $howmany = $alEv->send_alert(array(
                    "name" => "Enviar mail de error",
                    "explanation" => "Mail de error",
                    "subject" => 'Notificacion de tarifas',
                    "to" => array($d['dato']),
                    "html" => $htmlEvent,
                    "family" => "Carga Bienvenida",
                    "from" => 'info@espaciolink.com',
                    "attachments" => '',
                    "identifier" => 0
                ));
                trigger_error('email ' . $d['dato'] . ' - ' . $howmany);
            }
            if ($tipo == 'SMS-ENVIADO') {
                trigger_error('sms ' . $d['dato'] . ' - ' . enviaSMS($d['dato'], 'Esto es una prueba de sms para el envio de planes y tarifas'));
            }
        }
        break;
    case "limpiarpoll":
        $mongo = new MYMONGODB();
        $mongo->borrar('conversacionesDemo', []);
        $mongo->borrar('eventosDemo', []);
        break;
    case "poll":
        $mongo = new MYMONGODB();
        $mongo1 = new MYMONGODB();
        $mongo->buscar('conversacionesDemo', ['conversacionCargada' => ['$exists' => false]]);
        $resp = [];
        while ($row = $mongo->siguiente()) {
            if (!isset($row['preguntaCargada'])) {
                $resp[] = $row;
                $mongo1->actualizar('conversacionesDemo', ['_id' => $mongo->String2MongoId($row['id'])], ['preguntaCargada' => 1]);
            } else {
                if ($row['respuesta'] != '') {
                    $resp[] = $row;
                    $mongo1->actualizar('conversacionesDemo', ['_id' => $mongo->String2MongoId($row['id'])], ['conversacionCargada' => 1]);
                }
            }
        }
        $json = $resp;
        break;
    case "getUsuario":
        $db = new MYSQLDB();
        if ($db->query($db->mkSQL("SELECT * FROM ususuarios WHERE usUsuarios_id=%N", $_SESSION[MID . "userId"]), 1, 0)) {
            $row = $db->fetchRow();
            $json['resp'] = strtoupper($row['usUsuarios_nombres'] . ' ' . $row['usUsuarios_apellidos']);
        }
        break;
    case "buscarCI":
        $d = jsonStart();
//        require_once("../comunes/classes/class.API.php");
//        $miapi = new API(); //instancio el API que necesito
//        $clavePublica = 'k7F4llwzTyJADzi6eIYeyV6NhPH6Rs9SdFggN1jydC9DmfLnamJRY5kuwuqpZtNw';
//        $clavePrivada = 'PJ|>Zjir2:cV7gAu?0Nt9=k*W2@SPo_IJZ=i!8gGu-X{lVwK?xLc45IiKM</=}Ft';
//
//        $ci = $d["ci"];
//        $ci = str_replace(' ', '', $ci);
//        if (strlen($ci == 9 || $ci == 12)) {
//            $ci = '0' . $ci;
//        }
//
//        $urlbdds = "http://bdds.espaciolink.com";
//        $metodo = "api_obtenerRegistroCivil";
//        $token = $miapi->generaToken($clavePrivada, $metodo);
//        $arr = [
//            "endPointHost" => $urlbdds,
//            "clavePublica" => $clavePublica,
//            "token" => $token,
//            "metodo" => $metodo,
//            "identificador" => $ci
//        ];
//        $bd_datos = json_decode_from_utf8($miapi->comWS($arr));
//        $json['resp'] = $bd_datos;
        $mongo = new MYMONGODB();
        $rand = range(1, 100);
        $c = $mongo->buscar('seguros_crm', [],[],[],[],$rand);
        $json['resp'] = '';
        if ($c > 0) {
            $row = $mongo->siguiente();
            $json['resp'] = 'ok';
            $json['cliente'] = $row;
        }
        break;
}
jsonEnd($json, $limpiar);

function enviaSMS($numero, $msj) {
    $url = "http://online.publimes.com:5000/Service.svc?wsdl";
    ////int idCliente que nos entrega nuestro proveedor                                                              4 - Mensaje Erróneo
    $IdCliente = 229;
    //string contrasenia que nos entrega nuestro proveedor
    $Contrasenia = "CRG15236MA";
    //string operadora inicial que se deb colocar de la operadora ejemplo C->Claro, M->movistart, A->CNT
    $Operadora = "C";
    if (strlen($msj) <= 160) {
        $parametros = array("idCliente" => $IdCliente, "contrasenia" => $Contrasenia, "operadora" => $Operadora,
            "numeroTelefonico" => $numero, "mensaje" => $msj);

        try {
            $client = new SoapClient($url);
            $response = $client->EnviarMensaje($parametros);
        } catch (Exception $exc) {
            return 6; //array("existio un problema en la comunicación con el proveedor" => 6);
        }
        return $response->EnviarMensajeResult;
//        switch ($response->EnviarMensajeResult) {
//            case 0:
//                return array("Mensaje fue enviado" => 0);
//                break;
//            case 1:
//                return array("El usuario o Clave están erróneos" => 1);
//                break;
//            case 2:
//                return array("Error de registro de información" => 2);
//                break;
//            case 3:
//                return array("Celular Erróneo" => 3);
//                break;
//            case 4:
//                return array("Mensaje Erróneo" => 4);
//                break;
//            default:
//                return array("Existe un problema con el mensaje que retorna el WS" => $response);
//                break;
//        }
    } else {
        return array("El tamaño del mensaje no cumple con la logitud permitida de 160 caracteres" => 5);
    }
}

?><?

//_FIN_DE_ARCHIVO    ?>