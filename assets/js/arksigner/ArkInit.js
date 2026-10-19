var ArkSignerCardReaderElements = {
    success: "",
}
var ArkSignerCertificateElements = {
    success: "",
}
var ArkSignerInitializeElements = {
    success: ""
}
var selectedESignIndex_ = 0;
var selectedTckkIndex_ = 0;
var ListArkSignerElements = {
    smartcarts: [],
    processedTerminalIndex: 0
}

var ark_pw = {
    terminals: [],

    processedTerminalIndex: 0,
    //onListTerminalsCompleted,

    listTerminalsAndCertificates: function (_onListTerminalsAndCertificatesCompleted) {
        ark_pw.onListTerminalsAndCertificatesCompleted = _onListTerminalsAndCertificatesCompleted;
        pwsigner.smartCard.listTerminals(ark_pw.onListTerminals, {});
    },

    onListTerminals: function (code, json) {
        if (code == PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {
            // terminal bilgisini parse et
            var _terminals = pwsigner.parseJSON(json);

            $.each(_terminals, function (i, terminal) {
                var a = {
                    library: Base64.decode(terminal.library),
                    slotId: Base64.decode(terminal.slotId),
                    terminal: Base64.decode(terminal.terminal),
                    providesPINReader: terminal.providesPINReader !== undefined ? Base64.decode(terminal.providesPINReader) : null,
                    certificates: []
                };

                // terminals array içerisini doldur
                ark_pw.terminals.push(a);
            });
            // sertifikalar listesini sorgula
            ark_pw.listCertificates();
        } else {
            if (ark_pw.onListTerminalsAndCertificatesCompleted) {
                ark_pw.onListTerminalsAndCertificatesCompleted(code, ark_pw.terminals);
            }
        }
    },

    listCertificates: function () {
        ark_pw.processedTerminalIndex = 0;
        ark_pw.listCertificateOnTerminal(ark_pw.terminals[ark_pw.processedTerminalIndex].library, ark_pw.terminals[ark_pw.processedTerminalIndex].slotId);
    },

    listCertificateOnTerminal: function (library, slotId) {
        pwsigner.smartCard.listCertificates(ark_pw.onListCertificateOnTerminal, {
            library: library,
            slotId: slotId
        });
    },

    onListCertificateOnTerminal: function (code, json) {
        $("#CardReaderStep").addClass("dm-step-warning");
        if (code == PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {

            $("#CardReaderStep").removeClass("dm-step-warning");
            $("#CardReaderStep").addClass("dm-step-success");

            // sertifika bilgisini parse et ve js nesnesi olarak ekle
            var _certificates = pwsigner.parseJSON(json);

            $(_certificates).each(function (j, element) {
                var cert = {
                    serialNumber: Base64.decode(element.serialNumber),
                    commonName: Base64.decode(element.commonName),
                    subjectCommonName: Base64.decode(element.subjectCommonName),
                    certificateHex: hexToBase64(element.certificateHex),
                    issuer: Base64.decode(element.issuer),
                    notAfter: Base64.decode(element.notAfter),
                    notBefore: Base64.decode(element.notBefore),
                    subjectSerialNumber: Base64.decode(element.subjectSerialNumber)
                };


                $.ajax({
                    url: "/ArkSigner/CertificateControl",
                    method: "POST",
                    data: {
                        certHex: cert.certificateHex
                    },
                    success: function (result) {
                        HoldOnClose();
                        if (result.success) {
                            ark_pw.terminals[ark_pw.processedTerminalIndex].certificates.push(cert);
                            // exit condition'ı kontrol et
                            ark_pw.processedTerminalIndex = ark_pw.processedTerminalIndex + 1;
                            if (ark_pw.processedTerminalIndex < ark_pw.terminals.length) {
                                var __terminal = ark_pw.terminals[ark_pw.processedTerminalIndex];
                                ark_pw.listCertificateOnTerminal(__terminal.library, __terminal.slotId);
                            } else {
                                if (ark_pw.onListTerminalsAndCertificatesCompleted) {
                                    ark_pw.onListTerminalsAndCertificatesCompleted(code, ark_pw.terminals);
                                }
                            }
                        }
                    },
                    error: function (result) {
                        toastr.error("İşlem sırasında bir hata meydana geldi.");
                    }
                });
                ArkSignerCertificateElements.success = true;
            });// end of foreach
        }
    }
};

$(document).ready(function () {
    SetLicenseForInitialize();
});
function SetLicenseForInitialize() {
    $(".dm-sign-container").removeClass("dm-sign-c-success");
    pwsigner.setLicenseKey($("#license").val());
    pwsigner.inDebugMode = false;
    InitESign();
}
function InitESign() {
    CleanStepIcons();
    HoldOnOpen("Uygulama gereksinimleri kontrol ediliyor")
    pwsigner.initialize(onPWSigner_Initialized);
}
function onPWSigner_Initialized(code, json) {
    if (code == PWSigner.prototype.codes.ERROR_CANNOT_LOAD_LICENSE) {
        HoldOnClose();
        ArkSignerInitializeElements.success = false;
        $(".dm-sign-container").html(' ');
        $(".dm-sign-container").append(GetInitializeErrorPreview());
        return;
    }

    HoldOnClose();
    if (code == PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {
        HoldOnOpen("Sertifikalarınız listeleniyor ve doğrulanıyor...");
        ArkSignerInitializeElements.success = true;
        $("#ArkSignerSetup").removeClass("dm-step-warning");
        $("#ArkSignerSetup").addClass("dm-step-success");

        ark_pw.terminals = [];
        ark_pw.processedTerminalIndex = 0;

        ark_pw.listTerminalsAndCertificates(function (code, terminals) {
            $("#CertificateStep").addClass("dm-step-warning");
            HoldOnClose();
            if (code != PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {
                $("#CertificateStep").removeClass("dm-step-warning");
                $("#CardReaderStep").addClass("dm-step-warning");

                ArkSignerCardReaderElements.success = false;
                GetTerminalsNotFoundPreview();
            }
            else {
                $("#CertificateStep").removeClass("dm-step-warning");
                $("#CertificateStep").addClass("dm-step-success");

                ArkSignerCardReaderElements.success = true;
                PinCodeControl();
            }
        });

    } else {
        GetInitializeNotFoundPreview();
        ArkSignerInitializeElements.success = false;
        processErrorCode(code, json);
    }
}
function onPWSigner_ListTerminals(code, json) {
    $("#CardReaderStep").addClass("dm-step-warning");
    if (code == PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {
        $("#CardReaderStep").removeClass("dm-step-warning");
        $("#CardReaderStep").addClass("dm-step-success");
        var _terminals = pwsigner.parseJSON(json);

        HoldOnOpen("Sertifikalarınız listeleniyor ve doğrulanıyor...");
        $.each(_terminals, function (index, element) {
            var terminals = {
                library: Base64.decode(element.library),
                slotId: Base64.decode(element.slotId),
                terminal: Base64.decode(element.terminal),
                providesPINReader: element.providesPINReader !== undefined ? Base64.decode(element.providesPINReader) : null,
                certificates: []
            }
            ListArkSignerElements.smartcarts.push(terminals);
            ArkSignerCardReaderElements.success = true;

        });
    }
    else {
        ArkSignerCardReaderElements.success = false;
        GetTerminalsNotFoundPreview();
    }
}

function onPWSigner_ListCertificates(code, json) {
    $("#CertificateStep").addClass("dm-step-warning");
    if (code == PWSigner.prototype.codes.RESPONSE_SUCCESSFUL) {
        $("#CertificateStep").removeClass("dm-step-warning");
        $("#CertificateStep").addClass("dm-step-success");
        var _certificates = pwsigner.parseJSON(json);
        $(_certificates).each(function (index, element) {
            if (index == 0) {
                var cert = {
                    serialNumber: Base64.decode(element.serialNumber),
                    commonName: Base64.decode(element.commonName),
                    subjectCommonName: Base64.decode(element.subjectCommonName),
                    certificateHex: hexToBase64(element.certificateHex),
                    issuer: Base64.decode(element.issuer),
                    notAfter: Base64.decode(element.notAfter),
                    notBefore: Base64.decode(element.notBefore),
                    subjectSerialNumber: Base64.decode(element.subjectSerialNumber)
                };

                $.ajax({
                    url: "/ArkSigner/CertificateControl",
                    method: "POST",
                    data: {
                        certHex: cert.certificateHex
                    },
                    success: function (result) {
                        HoldOnClose();
                        if (result.success) {
                            ListArkSignerElements.smartcarts[ListArkSignerElements.processedTerminalIndex].certificates.push(cert);
                            ListArkSignerElements.processedTerminalIndex++;
                            if (ListArkSignerElements.processedTerminalIndex < ListArkSignerElements.smartcarts.length) {
                                var __terminal = ListArkSignerElements.smartcarts[ListArkSignerElements.processedTerminalIndex];
                                pwsigner.smartCard.listCertificates(onPWSigner_ListCertificates, {
                                    library: Base64.decode(__terminal.library),
                                    slotId: Base64.decode(__terminal.slotId)
                                });
                            }
                            else {
                                PinCodeControl();
                            }
                        }
                    },
                    error: function (result) {
                        toastr.error("İşlem sırasında bir hata meydana geldi.");
                    }
                });
                ArkSignerCertificateElements.success = true;
            }
        });
    }
    else {
        ArkSignerCertificateElements.success = false;
        HoldOnClose();
        processErrorCode(code, json);
    }
}
function GetCertificateForPreview() {
    $.ajax({
        url: "/ArkSigner/GetCertificateDetail",
        method: "POST",
        data: {
            'ArkSignerElements': ListArkSignerElements
        },
        success: function (result) {
            if (result.success) {
                $(".dm-sign-container").html(' ');
                $.each(result.htmlData, function (key, value) {
                    $(".dm-sign-container").append(value);
                });
            } else {
                $("#CertificateStep").addClass("dm-step-warning");
                $("#CertificateStep").removeClass("dm-step-success");
                $(".dm-sign-container").html(' ');
                $.each(result.htmlData, function (key, value) {
                    $(".dm-sign-container").append(value);
                });
            }

        },
        error: function (result) {
            toastr.error("İşlem sırasında bir hata meydana geldi");
        }
    });
}
function GetInitializeNotFoundPreview() {
    $.ajax({
        url: "/ArkSigner/ArkSignerInit",
        method: "POST",
        contentType: "application/json",
        success: function (result) {
            $("#ArkSignerSetup").addClass("dm-step-warning");
            $(".dm-sign-container").html(' ');
            $(".dm-sign-container").append(result.htmlData);
        },
        error: function (result) {
            toastr.error("İşlem sırasında bir hata meydana geldi");
        }
    });
}
function GetInitializeErrorPreview() {
    var licenseError = "";
    licenseError += "<div class='dm-content-h'>";
    licenseError += "<div class='dm-alert-box'><img class='dm-warning-icon' src='/Content/img/icon-warning.svg'></div>";
    licenseError += "<h4>ArkSigner Lisans Hatası</h4>";
    licenseError += "</div>";
    licenseError += "<p>ArkSigner lisansı yüklenirken bir hata oluştu. Destek ekibiyle iletişime geçiniz.</p>";
    return licenseError;

}
function GetTerminalsNotFoundPreview() {
    $.ajax({
        url: "/ArkSigner/NotFoundTerminal",
        method: "POST",
        contentType: "application/json",
        success: function (result) {
            $(".dm-sign-container").html(' ');
            $(".dm-sign-container").append(result);

            $(".dm-eid-container").html(' ');
            $(".dm-eid-container").append(result);
        },
        error: function (result) {
            toastr.error("İşlem sırasında bir hata meydana geldi");
        }

    });
}
function PinCodeControl() {
    $.ajax({
        url: "/ArkSigner/PinCodeControl",
        method: "POST",
        data: {
            'ArkSignerElements': GetArkSignerElementsFromArkPW()
        },
        success: function (result) {
            if (result.success) {
                $(".dm-sign-container").html(' ');
                $.each(result.htmlData, function (key, value) {
                    $(".dm-sign-container").append(value);
                });

                if (ark_pw.terminals[selectedESignIndex_].providesPINReader === "true") {
                    $(".dm-keybutton-group").hide();
                    $("#itxtPinCode").hide();
                }

                $("#esigncarouselprev").click(function () {
                    selectedESignIndex_ = Math.abs(--selectedESignIndex_) % $(".esignCarouselItems").length;
                    $("#hiddenSmartValue").val(selectedESignIndex_);

                    if (ark_pw.terminals[selectedESignIndex_].providesPINReader === 'true') {
                        $("#itxtPinCode").hide();
                        $(".dm-keybutton-group").hide();
                    }
                    else {
                        $("#itxtPinCode").show();
                        $(".dm-keybutton-group").show();
                    }
                });

                $("#esigncarouselnext").click(function () {
                    selectedESignIndex_ = ++selectedESignIndex_ % $(".esignCarouselItems").length;
                    $("#hiddenSmartValue").val(selectedESignIndex_);

                    if (ark_pw.terminals[selectedESignIndex_].providesPINReader === 'true') {
                        $("#itxtPinCode").hide();
                        $(".dm-keybutton-group").hide();
                    }
                    else {
                        $("#itxtPinCode").show();
                        $(".dm-keybutton-group").show();
                    }
                });

                $(document).on("click", "#tckkcarouselprev", function () {
                    selectedTckkIndex_ = ++selectedTckkIndex_ % $(".tckkCarouselItems").length;
                    $("#hiddenTckkDeviceValue").val(selectedTckkIndex_);
                });

                $(document).on("click", "#tckkcarouselnext", function () {
                    selectedTckkIndex_ = Math.abs(--selectedTckkIndex_) % $(".tckkCarouselItems").length;
                    $("#hiddenTckkDeviceValue").val(selectedTckkIndex_);
                });

            } else {
                $("#CertificateStep").addClass("dm-step-warning");
                $("#CertificateStep").removeClass("dm-step-success");
                $(".dm-sign-container").html(' ');
                $.each(result.htmlData, function (key, value) {
                    $(".dm-sign-container").append(value);
                });
            }

            HoldOnClose();
        },
        error: function (result) {
            HoldOnClose();
            toastr.error("İşlem sırasında bir hata meydana geldi");
        }
    });
};
var GetArkSignerElementsFromArkPW = function () {
    var smartcards = [];
    for (var i = 0; i < ark_pw.terminals.length; i++) {
        var terminalWithCerts = new Object();

        terminalWithCerts.slotId = ark_pw.terminals[i].slotId;
        terminalWithCerts.library = ark_pw.terminals[i].library;
        terminalWithCerts.terminal = ark_pw.terminals[i].terminal;

        terminalWithCerts.certificates = GetArkPwCertsFromArkPwTerminal(ark_pw.terminals[i]);

        smartcards.push(terminalWithCerts);
    }

    var terminals = {
        smartcarts: smartcards,
        processedTerminalIndex: 0
    }

    return terminals;
}

var GetArkPwCertsFromArkPwTerminal = function (terminal) {
    var certs = [];
    for (var i = 0; i < terminal.certificates.length; i++) {
        var cert = new Object();
        cert.serialNumber = terminal.certificates[i].serialNumber
        cert.commonName = terminal.certificates[i].commonName;
        cert.subjectCommonName = terminal.certificates[i].subjectCommonName;
        cert.certificateHex = terminal.certificates[i].certificateHex;
        cert.issuer = terminal.certificates[i].issuer;
        cert.notAfter = terminal.certificates[i].notAfter;
        cert.notBefore = terminal.certificates[i].notBefore;
        cert.subjectSerialNumber = terminal.certificates[i].subjectSerialNumber;

        certs.push(cert);
    }

    return certs;
};

function CleanStepIcons() {
    $("#ArkSignerSetup").removeClass("dm-step-warning");
    $("#ArkSignerSetup").removeClass("dm-step-success");

    $("#CardReaderStep").removeClass("dm-step-warning");
    $("#CardReaderStep").removeClass("dm-step-success");

    $("#CertificateStep").removeClass("dm-step-warning");
    $("#CertificateStep").removeClass("dm-step-success");

    $("#PinCodeStep").removeClass("dm-step-warning");
    $("#PinCodeStep").removeClass("dm-step-success");
}