<?php

use App\Helper\Form;
use App\Helper\Sube;

$Sube = new Sube();

?>


<div class="row mb-3">
    <div class="col-md-12">
        <button type="button" id="saveButton" class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                class="bx bx-save label-icon"></i> Kaydet</button>
    </div>
</div>


<form action="" id="uyeForm">
    <input type="hidden" name="uye_id" id="uye_id" class="form-control" value="<?php echo $id; ?>">

    <div class="row mb-3">

        <div class="col-md-3">
            <?php
            echo Form::FormFloatInput(
                "text",
                "uye_no",
                $uye_no ?? "",
                "Üye No giriniz!",
                "Üye No",
                "hash",
                "form-control"
            ); ?>
        </div>

        <div class="col-md-3">
            <?php echo Form::FormFloatInput(
                "text",
                "adi_soyadi",
                $uye->adi_soyadi ?? "",
                "Ad Soyad giriniz!",
                "Adı Soyadı",
                "user"
            ); ?>
        </div>
        <div class="col-md-3">
            <?php echo Form::FormFloatInput(
                "text",
                "tc_kimlik",
                $uye->tc_kimlik ?? "",
                "11 Haneli Tc Kimlik No giriniz!",
                "Tc Kimlik No",
                "user",
                "form-control number"

            ); ?>
        </div>
        <div class="col-md-3">

            <?php echo Form::FormFloatInput(
                "text",
                "unvan",
                $uye->unvan ?? "",
                "Unvan giriniz!",
                "Unvanı",
                "briefcase"
            ); ?>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-3">
            <?php echo
            Form::FormFloatInput(
                "text",
                name: "dogum_tarihi",
                value: $uye->dogum_tarihi ?? "",
                placeholder: "Doğum Tarihi giriniz!",
                label: "Doğum Tarihi",
                icon: "calendar",
                class: "form-control flatpickr",
                autocomplete: "off"

            ); ?>
        </div>
        <div class="col-md-3">
            <?php
            echo Form::FormFloatInput(
                "text",
                "telefon",
                $uye->telefon ?? "",
                "Telefon giriniz!",
                "Telefon",
                "phone",
                "form-control phone"
            ); ?>
        </div>
        <div class="col-md-3">

            <!-- Email -->
            <?php
            echo Form::FormFloatInput(
                "text",
                "email",
                $uye->email ?? "",
                "Email giriniz!",
                "Email",
                "mail"
            ); ?>
        </div>

        <div class="col-md-3">

            <?php

            echo Form::FormSelect2(
                name: "uye_il",
                options: $City->getCityList(),
                valueField: "id",
                textField: "city_name",
                selectedValue: $uye->il ?? 81,
                label: "İl",
                icon: "map-pin"
            ); ?>

        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-3">
            <!-- Çalıştığı Kurum -->
            <?php
            echo Form::FormFloatInput(
                "text",
                "calistigi_kurum",
                $uye->kurumu ?? "",
                "Çalıştığı kurumu giriniz!",
                "Çalıştığı Kurum",
                "box"
            ); ?>
        </div>
        <div class="col-md-6">
            <!-- Çalıştığı Kurum -->
            <?php
            echo Form::FormFloatInput(
                "text",
                "calistigi_birim",
                $uye->birimi ?? "",
                "Çalıştığı birimi giriniz!",
                "Çalıştığı Birim",
                "grid"
            ); ?>
        </div>
        <div class="col-md-3">
            <?php
            echo Form::FormSelect2(
                "uye_sube",
                $Sube->getSubeList(),
                $uye->sube_id ?? 1,
                "Şubesi",
                "map-pin",
                "id",
                "sube_adi"
            ); ?>

        </div>
    </div>
</form>
