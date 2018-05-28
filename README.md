# mikbill-custom
### Скрипты написанные чтобы что-то научить делать MikBill ( http://www.mikbill.ru )

* ## Начнем с СМС

### Для сервиса smspilot !

Вообщем выбрали мы провайдера для отправки http://smspilot.ru
И вроде билингом потдерживаеться, но выяснилось что проше допилить руками, а точнее поскали на сайте на буквн G)
Есть вот такие чудеснетные скрипты http://forum.forbill.com.ua/index.php?/topic/4-new-sms-opoveschenie-abonentov-sms-ukraine-sms-pilot-sms-fly-sms-beelineturbo-sms/ (спасибо вам ребята!) 

Еще использовалась инфа с ресурсов:
* https://mikbill.userecho.com/communities/1/topics/313-svoj-shlyuz-sms
* https://wiki.mikbill.ru/billing/preferences/smspilot

### И так теперь что получить и как пользоваться)

#+BEGIN_SRC bash

    cd ~
    git clone https://github.com/gochankot/mikbill-custom
    cd ./mikbill-custom/
    sudo cp -rf smspilot /var/www/mikbill/admin/res
    sudo cp -rf scripts /var/www/mikbill/admin/sys
    sudo chmod -R 644 /var/www/mikbill/admin/res/smspilot/*
    sudo chown apache:apache /var/www/mikbill/admin/res/smspilot/*
    sudo chmod 755 /var/www/mikbill/admin/sys/scripts/mikbill_sms_*.sh
    sudo chown apache:apache /var/www/mikbill/admin/sys/scripts/mikbill_sms_*.sh

#+END_SRC

Тоесть, мы склонировали мой репозитарий, положили содержимое в нужные катологи, и исправили права над доступ, а также валадельца/групп на нужную.

Внутри там
scripts:
mikbill_sms_loginpass.sh  mikbill_sms_notifpay.sh

smspilot:
sms_loginpass.php  sms_notifpay.php  smspilot.php

#### Теперь идем в smspilot.php
И правим SMSPILOT_APIKEY
#+BEGIN_SRC bash

    sudo vim /var/www/mikbill/admin/res/smspilot/smspilot.php

#+END_SRC

#### Также идем в оставшиеся 2 файла sms_loginpass.php ; sms_notifpay.php
И правим название в $COMPANY на ваше имя отправителя и остальное по желанию
#+BEGIN_SRC bash

    sudo vim /var/www/mikbill/admin/res/smspilot/sms_notifpay.php
    sudo vim /var/www/mikbill/admin/res/smspilot/sms_loginpass.php

#+END_SRC

Вместо vim впишите тот который вам удобен (vim/nano/mcedit)

#### И наканец мы можем запустить скрипты
* mikbill_sms_loginpass.sh Отправляем все у кого указан сотовый телефон в поле "Телефон для смс" их ЛОГИНЫ/ПАРОЛИ
(!!! НЕ ЗАБУДТЕ ПОПРАВИТЬ адрес личного кабина с stat.local.isp на свой)
Запуск
#+BEGIN_SRC bash

    /var/www/mikbill/admin/res/smspilot/sys/scripts/mikbill_sms_loginpass.sh

#+END_SRC

* А mikbill_sms_notifpay.sh - скрипт для авто напоминания (за 2 дня до 1го числа), и его надо добавить в cron
#+BEGIN_SRC bash

    crontab -e

#+END_SRC

#### копируем и вставляем вот эту строку в низ списка

#+BEGIN_SRC bash
    0 9 * * *  root [ "$(date -d "$m/1 + 1 month - 2 day" "+%d")" -eq "$(date "+%d")" ] && /var/www/mikbill/admin/res/smspilot/sms_notifpay.sh
#+END_SRC

И затем запускаем cron (но скорее всего он ранее запущен)

#+BEGIN_SRC bash

    sudo service crontab start
    sudo chkconfig crond on

#+END_SRC


#### TODO: 
* положить и написать описание для скрипт информировани о внесении денег
* ... оставим пустую строчку)
* сделать канибуть отправку логина пароля из админки
