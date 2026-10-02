using System;
using System.IO;
using System.Text;
using System.Linq;
using System.Collections.Generic;
using System.ServiceModel;
using System.Xml.Serialization;
using System.Text.RegularExpressions;
using System.Windows.Forms;

using System.Diagnostics;
using NLog;
using ConnectorClientSampleCommon.EdmService;
using ConnectorClientSampleCommon.Entities;
using ConnectorClientSampleCommon.Enums;

namespace CurrentEDMConnectorClientLibrary
{
    public partial class EFaturaEDMConnectorLibrary
    {
        private static Logger logger = LogManager.GetCurrentClassLogger();

        private EFaturaEDMPortClient _service;
        private REQUEST_HEADERType _requestHeader;

        private readonly string _sessionDestroyedMessage = "Aktif Session bulunamadi";

        private const string _incomingpath = "C:\\EDM";
        private const string _outgoingpath = "C:\\EDM";
        private const int sessionTryCount = 2;

        private static Regex timeZoneRegex = new Regex(@"(\d{2}:\d{2}:\d{2})(\.\d{7}[\+|-]\d{2}:\d{2})");

        public int EDMServer;
        public string EDMCorporate;
        public string EDMLogin;
        public string EDMPassw;
        public string EDMPostBox;
        public string EDMMessageResponseCaption = "EDM E-Dönüşüm::Uyarı";
        public string EDMMessageSystemCaption = "EDM E-Dönüşüm::Hata";
        public string EDMProxyServer;
        public string EDMProxyUser;
        public string EDMProxyPassw;

        public EFaturaEDMConnectorLibrary(string url)
        {
            BasicHttpBinding binding = new BasicHttpBinding();
            binding.Name = "IntegrationServiceSoap";
            binding.CloseTimeout = System.TimeSpan.Parse("00:01:00");
            binding.OpenTimeout = System.TimeSpan.Parse("00:01:00");
            binding.ReceiveTimeout = System.TimeSpan.Parse("00:01:00");
            binding.SendTimeout = System.TimeSpan.Parse("00:01:00");
            binding.AllowCookies = false;
            binding.BypassProxyOnLocal = false;
            binding.HostNameComparisonMode = System.ServiceModel.HostNameComparisonMode.StrongWildcard;
            binding.MaxBufferSize = 2147483647; // 65536;
            binding.MaxBufferPoolSize = 2147483647;
            binding.MaxReceivedMessageSize = 2147483647; // 65536;
            binding.MessageEncoding = System.ServiceModel.WSMessageEncoding.Text;
            binding.TextEncoding = System.Text.Encoding.UTF8;
            binding.TransferMode = System.ServiceModel.TransferMode.Buffered;
            binding.UseDefaultWebProxy = true;
            binding.ReaderQuotas.MaxArrayLength = 2147483647; // 16384;
            binding.ReaderQuotas.MaxBytesPerRead = 4096;
            binding.ReaderQuotas.MaxNameTableCharCount = 2147483647; // 16384;
            binding.Security.Mode = System.ServiceModel.BasicHttpSecurityMode.Transport;
            binding.Security.Transport.ClientCredentialType = HttpClientCredentialType.Windows;
            binding.Security.Transport.ProxyCredentialType = HttpProxyCredentialType.None;
            binding.Security.Transport.Realm = "";
            binding.Security.Message.ClientCredentialType = BasicHttpMessageCredentialType.Certificate;
            binding.Security.Message.AlgorithmSuite = System.ServiceModel.Security.SecurityAlgorithmSuite.Default;

            System.Net.ServicePointManager.ServerCertificateValidationCallback +=
            (se, cert, chain, sslerror) =>
            {
                return true;
            };

            EndpointAddress endpointadress;
            endpointadress = new EndpointAddress(new Uri(url));
            
            if (string.IsNullOrEmpty(EDMProxyServer) == false)
            {
                if (string.IsNullOrEmpty(EDMProxyUser) == false)
                    binding.Security.Transport.ProxyCredentialType = HttpProxyCredentialType.Basic;
                else binding.Security.Transport.ProxyCredentialType = HttpProxyCredentialType.None;
                binding.UseDefaultWebProxy = false;
                binding.ProxyAddress = new Uri(string.Format("http://{0}", EDMProxyServer.Trim()));
            }
            _service = new EFaturaEDMPortClient(binding, endpointadress);
            if (string.IsNullOrEmpty(EDMProxyServer) == false && string.IsNullOrEmpty(EDMProxyUser) == false)
            {
                _service.ClientCredentials.UserName.UserName = EDMProxyUser;
                _service.ClientCredentials.UserName.Password = EDMProxyPassw;
            }

            //firstly
            _requestHeader = new REQUEST_HEADERType();
            _requestHeader.SESSION_ID = "0";
            _requestHeader.CLIENT_TXN_ID = System.Guid.NewGuid().ToString();
            _requestHeader.APPLICATION_NAME = "EDM MINI CONNECTOR v1.0";
            _requestHeader.CHANNEL_NAME = string.Format("{0} company", (EDMServer == 5) ? "PROD" : "TEST");
            _requestHeader.HOSTNAME = "EDM MINI CONNECTOR v1.0";
            _requestHeader.ACTION_DATE = DateTime.Now;
            _requestHeader.ACTION_DATESpecified = true;
            _requestHeader.REASON = "E-fatura/E-Arşiv gönder-al-CANLI";
            _requestHeader.COMPRESSED = "N";
            //

        }
        
        // oturum açma-kapama
        #region login/logout
        /// <summary>
        /// EDM web servis oturumu açar(Session süresi:20dk).
        /// </summary>
        ///  <param name="outOfFuncRank">Bu parametre varsayılan değeri 2(sessionTryCount) olarak alır.Kendi halinde (sadece oturum açma için) çalıştığı zaman ekrana hatayı basacaktır.Nitelik dışı çağrılan durumlarda (outOfFunckRank) 2 ye denk gelmiyor ise EDMGetSession içerisinde oluşan exception hatası basılmaz.Eğer outOfFunckRank 2 ye denk gelmişse hatayı ekrana basar.Amacı : sessionTryCount son aşamada EDMGetSession 'dan olumlu yanıt alamıyorsa oturum açamama hatasını ekrana basar.</param>
        ///   ///  <param name="sessionExperimentCount">Fonksiyon denemeleri son buldu ise 1 değilse 0 </returns>
        public string EDMGetSession(int outOfFuncCount = sessionTryCount, int sessionExperimentCount = 0)
        {
            Exception systemError = null;

            string retVal = string.Empty;

            try
            {
                //request
                LoginRequest loginRequest = new LoginRequest();
                loginRequest.USER_NAME = EDMLogin;
                loginRequest.PASSWORD = EDMPassw;
                loginRequest.REQUEST_HEADER = _requestHeader;
                loginRequest.REQUEST_HEADER.ACTION_DATE = DateTime.Now;
                loginRequest.REQUEST_HEADER.ACTION_DATESpecified = true;
                loginRequest.REQUEST_HEADER.APPLICATION_NAME = "EDM MINI CONNECTOR v1.0";
                loginRequest.REQUEST_HEADER.CHANNEL_NAME = "TEST";
                loginRequest.REQUEST_HEADER.COMPRESSED = "N";
                loginRequest.REQUEST_HEADER.HOSTNAME = "MDORA17";
                loginRequest.REQUEST_HEADER.REASON = "E-fatura/E-Arşiv gönder-al testleri için";

                //response
                LoginResponse loginResponse = _service.Login(loginRequest);

                //return & result
                if (!String.IsNullOrEmpty(loginResponse.SESSION_ID))
                {
                    _requestHeader.SESSION_ID = loginResponse.SESSION_ID;

                    retVal = loginResponse.SESSION_ID;
                    return retVal;
                }
            }
            catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
            {
                systemError = fexp;

                //test
                CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
            }
            catch (System.ServiceModel.FaultException fex)
            {
                systemError = fex;
            }
            catch (Exception ex)
            {
                systemError = ex;
            }
            finally
            {
                //outOfFuncCount:nitelik dışı çağrılma sayısı
                //sessionTryCount:session denemeleri en çok kaç kez
                if (outOfFuncCount == sessionTryCount)
                {
                    // sessionExperimentCount 1 olmuşsa 'oturum denemeleri sona erdi' 
                    if (systemError != null && sessionExperimentCount < 1)
                    {
                        ShowSystemErrMessage(systemError);
                    }
                    if (sessionExperimentCount > 0)
                    {
                        Exception experimentEndMsg = new System.Exception("Oturum denemeleri sona erdi");
                        ShowSystemErrMessage(experimentEndMsg);
                    }
                }
            }

            return retVal;
        }

        /// <summary>
        /// EDM web servis oturumu açar(Session süresi:20dk).
        /// </summary>
        /// <returns>Status</returns>
        public int EDMCloseSession()
        {
            Exception systemError = null;

            int retVal = 1;
            try
            {
                //request
                LogoutRequest logoutRequest = new LogoutRequest();
                logoutRequest.REQUEST_HEADER = _requestHeader;

                //response
                LogoutResponse logoutResponse = _service.Logout(logoutRequest);

                //return & result
                return 0;
            }
            catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
            {
                systemError = fexp;

                //test
                CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
            }
            catch (System.ServiceModel.FaultException fex)
            {
                systemError = fex;
            }
            catch (Exception ex)
            {
                systemError = ex;
            }
            finally
            {
                if (systemError != null)
                {
                    ShowSystemErrMessage(systemError);
                }
                retVal = (systemError == null) ? retVal : -1;
            }

            return retVal;

        }

        #endregion


        // Fatura gönderimleri..
        #region e-Fatura e-Arşiv gönderimi

        public int SendInvoiceMultiple(string ubldir,
                                                string[] ublfilepaths,
                                                int multiplexcount = 1,
                                                bool createUUID = true,
                                                string invoiceid = null,
                                                string pk = null,
                                                string gb = null,
                                                string receiverMail = null,
                                                string receiverVKNTCKN = null,
                                                string senderVKNTCKN = null,
                                                string mobile = null,
                                                bool generateInvoiceID = false)
        {

            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    #region edm example

                    List<INVOICE> cntrinvoiceList = new List<INVOICE>();


                    foreach (var item in ublfilepaths)
                    {
                        string ublfilefullpath = @"" + ubldir + @"\" + item;
                        byte[] ublfilebytes = File.ReadAllBytes(ublfilefullpath);

                        hm.common.Ubltr.Invoice21.InvoiceType ublinvoice
                            = hm.common.Ubltr.Invoice21.InvoiceType.DeserializeF(System.Text.Encoding.UTF8.GetString(ublfilebytes));

                        // smooth serialize
                        string invoiceUblXmlStr
                            = ublinvoice.SerializeF();

                        if (createUUID)
                        {
                            ublinvoice.UUID.Value = Guid.NewGuid().ToString();
                        }

                        if (ublinvoice.ID == null)
                        {
                            ublinvoice.ID = new hm.common.Ubltr.Invoice21.IDType();
                        }

                        if (!string.IsNullOrEmpty(invoiceid))
                        {
                            ublinvoice.ID.Value = invoiceid;
                        }

                        /// set receiver sample

                        if (string.IsNullOrEmpty(receiverVKNTCKN))
                        {
                            receiverVKNTCKN = ublinvoice.AccountingCustomerParty.Party.PartyIdentification.Where(t => t.ID.schemeID == "VKN" || t.ID.schemeID == "TCKN").First().ID.Value;
                        }
                        if (string.IsNullOrEmpty(senderVKNTCKN))
                        {
                            senderVKNTCKN = ublinvoice.AccountingSupplierParty.Party.PartyIdentification.Where(t => t.ID.schemeID == "VKN" || t.ID.schemeID == "TCKN").First().ID.Value;
                        }

                        //ublinvoice.ProfileID.Value = "EARSIVFATURA";

                        //ublinvoice.AccountingSupplierParty.Party.PartyIdentification.First().ID.Value = "3230512384";
                        //foreach (var customerparty in ublinvoice.AccountingCustomerParty.Party.PartyIdentification)
                        //{
                        //    if (customerparty.ID.schemeID == "TCKN")
                        //    {
                        //        customerparty.ID.schemeID = "VKN";
                        //    }
                        //    customerparty.ID.Value = "1245548126";

                        //}

                        if (generateInvoiceID)
                        {
                            ublinvoice.ID.Value = "ABC2009123456789";
                        }

                        //ublinvoice.ID.Value = "EA22018000000001";
                        //ublinvoice.IssueDate.Value = DateTime.Now.AddDays(-1);

                        //ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "TURKEY";

                        //if (ublinvoice.InvoiceTypeCode.Value == "ISTISNA")
                        //{
                        //    ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "ENGLAND";
                        //}

                        CheckUserRequest checkUserRequest = new CheckUserRequest();
                        checkUserRequest.USER = new GIBUSER();
                        checkUserRequest.USER.IDENTIFIER = receiverVKNTCKN;
                        checkUserRequest.REQUEST_HEADER = _requestHeader;

                        GIBUSER[] gibusers = _service.CheckUser(checkUserRequest);

                        // create connector invoice
                        INVOICE cntrinvoice = new INVOICE();

                        //cntrinvoice.ID = ublinvoice.ID.Value;//ublinvoice.ID.Value;
                        //cntrinvoice.UUID = ublinvoice.UUID.Value;//ublinvoice.UUID.Value;
                        cntrinvoice.HEADER = new INVOICEHEADER()
                        {

                            SENDER = senderVKNTCKN,
                            FROM = gb,
                            //FROM = "urn:mail:defaultgb@buluttest.com.tr",
                            RECEIVER = receiverVKNTCKN,
                            //TO = "urn:mail:" + pk,
                            TO = pk,
                            //TO = "urn:mail:defaultpk@buluttest.com.tr",
                            //TO = pk,
                            //INVOICESERIAL_REQUESTED = "MEM"
                            MOBILE = mobile,  //12 rakamdan oluşmalı
                        };

                        invoiceUblXmlStr = ublinvoice.SerializeF();
                        invoiceUblXmlStr = timeZoneRegex.Replace(invoiceUblXmlStr, "$1");
                        ublfilebytes = System.Text.Encoding.UTF8.GetBytes(invoiceUblXmlStr);

                        cntrinvoice.CONTENT = new ConnectorClientSampleCommon.EdmService.base64Binary()
                        {
                            Value = ublfilebytes
                        };

                        cntrinvoiceList.Add(cntrinvoice);

                    }
                    #endregion

                    #region Send Invoice
                    ////request
                    //SendInvoiceRequest sendInvoiceRequest = new SendInvoiceRequest();
                    SendInvoiceRequest sendInvoiceRequest = new SendInvoiceRequest();

                    sendInvoiceRequest.REQUEST_HEADER = _requestHeader;

                    sendInvoiceRequest.RECEIVER = new SendInvoiceRequestRECEIVER()
                    {
                        vkn = cntrinvoiceList[0].HEADER.RECEIVER,
                        alias = cntrinvoiceList[0].HEADER.TO,
                    };
                    sendInvoiceRequest.INVOICE = cntrinvoiceList.ToArray();

                    //response
                    SendInvoiceResponse sendInvoiceResponse = _service.SendInvoice(sendInvoiceRequest);

                    //return & result
                    retVal = 0;

                    break;

                    #endregion

                    #region Load Invoice
                    //request
                    //SendInvoiceRequest sendInvoiceRequest = new SendInvoiceRequest();
                    //LoadInvoiceRequest loadInvoiceRequest = new LoadInvoiceRequest();

                    //loadInvoiceRequest.REQUEST_HEADER = _requestHeader;

                    //loadInvoiceRequest.RECEIVER = new LoadInvoiceRequestRECEIVER()
                    //{
                    //    vkn = cntrinvoiceList[0].HEADER.RECEIVER,
                    //    alias = cntrinvoiceList[0].HEADER.TO,
                    //};
                    //loadInvoiceRequest.INVOICE = cntrinvoiceList.ToArray();

                    ////response
                    //LoadInvoiceResponse loadInvoiceResponse = _service.LoadInvoice(loadInvoiceRequest);

                    ////return & result
                    //retVal = 0;

                    //break;

                    #endregion

                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;

        }


        public int SendDraftInvoice(string ubldir,
                                                string[] ublfilepaths,
                                                int multiplexcount = 1,
                                                bool createUUID = true,
                                                string invoiceid = null,
                                                string pk = null,
                                                string gb = null,
                                                string receiverMail = null,
                                                string receiverVKNTCKN = null,
                                                string senderVKNTCKN = null,
                                                string mobile = null,
                                                bool generateInvoiceID = false)
        {

            Exception responseError = null;
            Exception systemError = null;

            try
            {
                #region edm example

                List<INVOICE> cntrinvoiceList = new List<INVOICE>();


                foreach (var item in ublfilepaths)
                {
                    string ublfilefullpath = @"" + ubldir + @"\" + item;
                    byte[] ublfilebytes = File.ReadAllBytes(ublfilefullpath);

                    hm.common.Ubltr.Invoice21.InvoiceType ublinvoice
                        = hm.common.Ubltr.Invoice21.InvoiceType.DeserializeF(System.Text.Encoding.UTF8.GetString(ublfilebytes));

                    // smooth serialize
                    string invoiceUblXmlStr
                        = ublinvoice.SerializeF();

                    if (createUUID)
                    {
                        ublinvoice.UUID.Value = Guid.NewGuid().ToString();
                    }

                    if (ublinvoice.ID == null)
                    {
                        ublinvoice.ID = new hm.common.Ubltr.Invoice21.IDType();
                    }

                    if (!string.IsNullOrEmpty(invoiceid))
                    {
                        ublinvoice.ID.Value = invoiceid;
                    }

                    /// set receiver sample

                    if (string.IsNullOrEmpty(receiverVKNTCKN))
                    {
                        receiverVKNTCKN = ublinvoice.AccountingCustomerParty.Party.PartyIdentification.Where(t => t.ID.schemeID == "VKN" || t.ID.schemeID == "TCKN").First().ID.Value;
                    }
                    if (string.IsNullOrEmpty(senderVKNTCKN))
                    {
                        senderVKNTCKN = ublinvoice.AccountingSupplierParty.Party.PartyIdentification.Where(t => t.ID.schemeID == "VKN" || t.ID.schemeID == "TCKN").First().ID.Value;
                    }

                    //ublinvoice.ProfileID.Value = "EARSIVFATURA";

                    //ublinvoice.AccountingSupplierParty.Party.PartyIdentification.First().ID.Value = "3230512384";
                    //foreach (var customerparty in ublinvoice.AccountingCustomerParty.Party.PartyIdentification)
                    //{
                    //    if (customerparty.ID.schemeID == "TCKN")
                    //    {
                    //        customerparty.ID.schemeID = "VKN";
                    //    }
                    //    customerparty.ID.Value = "1245548126";

                    //}

                    if (generateInvoiceID)
                    {
                        ublinvoice.ID.Value = "ABC2009123456789";
                    }

                    //ublinvoice.ID.Value = "EA22018000000001";
                    //ublinvoice.IssueDate.Value = DateTime.Now.AddDays(-1);

                    //ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "TURKEY";

                    //if (ublinvoice.InvoiceTypeCode.Value == "ISTISNA")
                    //{
                    //    ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "ENGLAND";
                    //}

                    //CheckUserRequest checkUserRequest = new CheckUserRequest();
                    //checkUserRequest.USER = new GIBUSER();
                    //checkUserRequest.USER.IDENTIFIER = receiverVKNTCKN;
                    //checkUserRequest.REQUEST_HEADER = _requestHeader;

                    //GIBUSER[] gibusers = _service.CheckUser(checkUserRequest);

                    // create connector invoice
                    INVOICE cntrinvoice = new INVOICE();


                    //cntrinvoice.ID = ublinvoice.ID.Value;//ublinvoice.ID.Value;
                    //cntrinvoice.UUID = ublinvoice.UUID.Value;//ublinvoice.UUID.Value;
                    cntrinvoice.HEADER = new INVOICEHEADER()
                    {

                        SENDER = senderVKNTCKN,
                        FROM = gb,
                        //FROM = "urn:mail:defaultgb@buluttest.com.tr",
                        RECEIVER = receiverVKNTCKN,
                        //TO = "urn:mail:" + pk,
                        TO = pk,
                        //TO = "urn:mail:defaultpk@buluttest.com.tr",
                        //TO = pk,
                        //INVOICESERIAL_REQUESTED = "MEM"
                        MOBILE = mobile,  //12 rakamdan oluşmalı
                    };

                    invoiceUblXmlStr = ublinvoice.SerializeF();
                    invoiceUblXmlStr = timeZoneRegex.Replace(invoiceUblXmlStr, "$1");
                    ublfilebytes = System.Text.Encoding.UTF8.GetBytes(invoiceUblXmlStr);

                    cntrinvoice.CONTENT = new ConnectorClientSampleCommon.EdmService.base64Binary()
                    {
                        Value = ublfilebytes
                    };


                    cntrinvoiceList.Add(cntrinvoice);

                }

                #endregion

                //request
                LoadInvoiceRequest loadInvoiceRequest = new LoadInvoiceRequest();

                loadInvoiceRequest.REQUEST_HEADER = _requestHeader;
                loadInvoiceRequest.GENERATEINVOICEIDONLOAD = true;

                loadInvoiceRequest.RECEIVER = new LoadInvoiceRequestRECEIVER()
                {
                    vkn = cntrinvoiceList[0].HEADER.RECEIVER,
                    alias = cntrinvoiceList[0].HEADER.TO,
                };
                loadInvoiceRequest.INVOICE = cntrinvoiceList.ToArray();

                //response
                LoadInvoiceResponse sendInvoiceResponse = _service.LoadInvoice(loadInvoiceRequest);

                //return & result

            }
            catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
            {
                responseError = fexp;

                //test
                CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
            }
            catch (System.ServiceModel.FaultException fex)
            {
                systemError = fex;
            }
            catch (Exception ex)
            {
                systemError = ex;
            }

            #region output err msg
            if (responseError != null)
            {

                //output response err msg

                ShowResponseErrMessage(responseError);

            }

            if (systemError != null)
            {
                //output system err msg

                ShowSystemErrMessage(systemError);
            }
            #endregion

            return 1;
        }

        public void SendInvoiceMultipleWithStressTest(string invoiceFindPath, string[] invoiceFileName, string userName, string password)
        {
            int funcIndex = 0;

            Exception responseError = null;
            Exception systemError = null;

            try
            {
                #region Login

                try
                {
                    _requestHeader = new REQUEST_HEADERType();
                    _requestHeader.SESSION_ID = "0";
                    _requestHeader.CLIENT_TXN_ID = System.Guid.NewGuid().ToString();
                    _requestHeader.APPLICATION_NAME = "EDM STRESS TEST";
                    _requestHeader.CHANNEL_NAME = "TEST";
                    _requestHeader.HOSTNAME = "EDM-STRESS-TEST";
                    _requestHeader.ACTION_DATE = DateTime.Now;
                    _requestHeader.ACTION_DATESpecified = true;
                    _requestHeader.REASON = "E-fatura/E-Arşiv gönder-al-TEST";
                    _requestHeader.COMPRESSED = "N";

                    //request
                    LoginRequest loginRequest = new LoginRequest();
                    loginRequest.USER_NAME = "cc_edmbulut2";
                    loginRequest.PASSWORD = "cc_edmbulut2";
                    loginRequest.REQUEST_HEADER = _requestHeader;

                    //response
                    LoginResponse loginResponse = _service.Login(loginRequest);

                    //return & result
                    if (!String.IsNullOrEmpty(loginResponse.SESSION_ID))
                    {
                        _requestHeader.SESSION_ID = loginResponse.SESSION_ID;
                    }
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    systemError = fexp;
                    Console.WriteLine(systemError);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                    Console.WriteLine(systemError);
                }
                catch (Exception ex)
                {
                    systemError = ex;
                    Console.WriteLine(systemError);
                }

                #endregion


                List<INVOICE> cntrinvoiceList = new List<INVOICE>();

                foreach (var item in invoiceFileName)
                {
                    string ublfilefullpath = @"" + invoiceFindPath + @"\" + item;
                    byte[] ublfilebytes = File.ReadAllBytes(ublfilefullpath);

                    hm.common.Ubltr.Invoice21.InvoiceType ublinvoice
                        = hm.common.Ubltr.Invoice21.InvoiceType.DeserializeF(System.Text.Encoding.UTF8.GetString(ublfilebytes));

                    // smooth serialize
                    string invoiceUblXmlStr
                        = ublinvoice.SerializeF();


                    ublinvoice.UUID.Value = Guid.NewGuid().ToString();


                    ublinvoice.ProfileID.Value = "EARSIVFATURA";

                    if (ublinvoice.AccountingSupplierParty != null)
                    {
                        if (ublinvoice.AccountingSupplierParty.Party != null)
                        {
                            if (ublinvoice.AccountingSupplierParty.Party.PartyIdentification != null)
                            {
                                if (ublinvoice.AccountingSupplierParty.Party.PartyIdentification.First().ID != null)
                                {
                                    if (ublinvoice.AccountingSupplierParty.Party.PartyIdentification.First().ID.Value != null)
                                    {
                                        ublinvoice.AccountingSupplierParty.Party.PartyIdentification.First().ID.Value = "0100033935";
                                    }
                                }
                            }
                        }
                    }


                    foreach (var customerparty in ublinvoice.AccountingCustomerParty.Party.PartyIdentification)
                    {
                        if (customerparty.ID.schemeID == "TCKN")
                        {
                            customerparty.ID.schemeID = "VKN";
                        }
                        customerparty.ID.Value = "1245548126";

                    }
                    ublinvoice.ID.Value = "ABC2009123456789";
                    //ublinvoice.ID.Value = "EA22018000000001";
                    ublinvoice.IssueDate.Value = DateTime.Now;

                    ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "TURKEY";

                    if (ublinvoice.InvoiceTypeCode.Value == "ISTISNA")
                    {
                        ublinvoice.AccountingCustomerParty.Party.PostalAddress.Country.Name.Value = "ENGLAND";
                    }


                    // create connector invoice
                    INVOICE cntrinvoice = new INVOICE();


                    //cntrinvoice.ID = ublinvoice.ID.Value;//ublinvoice.ID.Value;
                    //cntrinvoice.UUID = ublinvoice.UUID.Value;//ublinvoice.UUID.Value;
                    cntrinvoice.HEADER = new INVOICEHEADER()
                    {

                        //SENDER = "0100033935",//
                        //FROM = "urn:mail:" + gb,
                        FROM = "urn:mail:defaultgb@buluttest.com.tr",
                        //RECEIVER = "1245548126",
                        //TO = "urn:mail:" + pk,
                        TO = "muhammet.dora@edmbilisim.com.tr",
                        //TO = "urn:mail:defaultpk@buluttest.com.tr",
                        //TO = pk,
                        INVOICESERIAL_REQUESTED = "RAR"

                    };

                    invoiceUblXmlStr = ublinvoice.SerializeF();
                    invoiceUblXmlStr = timeZoneRegex.Replace(invoiceUblXmlStr, "$1");
                    ublfilebytes = System.Text.Encoding.UTF8.GetBytes(invoiceUblXmlStr);

                    cntrinvoice.CONTENT = new ConnectorClientSampleCommon.EdmService.base64Binary()
                    {
                        Value = ublfilebytes
                    };

                    cntrinvoiceList.Add(cntrinvoice);

                }


                //request
                SendInvoiceRequest sendInvoiceRequest = new SendInvoiceRequest();

                sendInvoiceRequest.REQUEST_HEADER = _requestHeader;

                sendInvoiceRequest.RECEIVER = new SendInvoiceRequestRECEIVER()
                {
                    vkn = cntrinvoiceList[0].HEADER.RECEIVER,
                    alias = cntrinvoiceList[0].HEADER.TO,
                };
                sendInvoiceRequest.INVOICE = cntrinvoiceList.ToArray();

                //response
                SendInvoiceResponse sendInvoiceResponse = _service.SendInvoice(sendInvoiceRequest);

                hm.common.Tools.logError(logger, "COMPLETED:" + funcIndex);
            }
            catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
            {
                hm.common.Tools.logError(logger, fexp.ToString());
                hm.common.Tools.logTrace(logger, "FUNC INDEX:" + funcIndex);
            }
            catch (System.ServiceModel.FaultException fex)
            {
                hm.common.Tools.logError(logger, fex.ToString());
                hm.common.Tools.logTrace(logger, "FUNC INDEX:" + funcIndex);
            }
            catch (Exception ex)
            {
                hm.common.Tools.logError(logger, ex.ToString());
                hm.common.Tools.logTrace(logger, "FUNC INDEX:" + funcIndex);
            }
            funcIndex++;

        }

        #endregion


        // fatura alma , indirme ve durum sorgulama
        #region GetInvoice & Status

        /// <summary>
        /// E-Fatura/E-Arşiv parametlerine bağlı faturalarınızın içeriklerini filtreleyerek indirmenizi sağlar.
        /// </summary>
        /// <param name="downloadcontent">true: kafa kaydı bilgileri, false: contenttype'a bağlı istenen fatura dosyası</param>
        /// <param name="contenttype">"XML", "PDF", "ZIP"</param>
        /// <param name="read_included">true: MARK işlemi ile işaretlenmiş olanları da listeler, false: MARK ie işaretlenmil olanları listeye dahil etmez</param>
        /// <param name="direction"> Gönderilenler için: "OUT" "OUT-EINVOICE"  "OUT-EARCHIVE" / Gelenler için:"IN"</param>
        /// <param name="limit">çekilen fatura adedi için limit </param>
        /// <param name="faturano">spesifik bir faturano</param>
        /// <param name="faturauuid">spesifik bir fatura ettn</param>
        /// <returns>Status</returns>
        public int GetInvoice(
            DateTime crStartDate
            , DateTime crEndDate
            , DateTime startDate
            , DateTime endDate
            , bool crStartDateChecked
            , bool startDateChecked
            , bool downloadcontent = false
            , string contenttype = "XML"
            , bool marked = false
            , string direction = "OUT"
            , int limit = 10
            , string faturano = null
            , string faturauuid = null
            , bool isArchived = false)
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            string directionPath = string.Empty;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    GetInvoiceRequestINVOICE_SEARCH_KEY invoiceSearchKey = new GetInvoiceRequestINVOICE_SEARCH_KEY();

                    //yalnız IN ise
                    if (direction.Contains("IN") && !direction.Contains("INVOICE"))
                        invoiceSearchKey.DIRECTION = "IN";

                    //tarihler
                    invoiceSearchKey.CR_START_DATE = crStartDate;
                    invoiceSearchKey.CR_START_DATESpecified = crStartDateChecked;
                    invoiceSearchKey.CR_END_DATE = crEndDate;
                    invoiceSearchKey.CR_END_DATESpecified = crStartDateChecked;

                    invoiceSearchKey.START_DATE = startDate;
                    invoiceSearchKey.START_DATESpecified = startDateChecked;
                    invoiceSearchKey.END_DATE = endDate;
                    invoiceSearchKey.END_DATESpecified = startDateChecked;

                    invoiceSearchKey.LIMIT = limit;
                    invoiceSearchKey.LIMITSpecified = limit > 0 ? true : false;//ekranda seçim yapılmamışsa dahi LIMIT olduğunu kabul eder (standart:100)

                    //ekranda seçim yapılmamışsa READ_INCLUDED false olarak kabul edilir. TRUE: okunanları da al  FALSE: yalnızca okunmamışları alsın
                    invoiceSearchKey.READ_INCLUDED = marked;
                    invoiceSearchKey.READ_INCLUDEDSpecified = marked;

                    invoiceSearchKey.ISARCHIVED = isArchived;
                    invoiceSearchKey.ISARCHIVEDSpecified = isArchived;//ekranda seçim yapılmamışsa ISARCHIVED false olarak kabul edilir.

                    invoiceSearchKey.ID = (String.IsNullOrEmpty(faturano) ? null : faturano);
                    invoiceSearchKey.UUID = (String.IsNullOrEmpty(faturauuid) ? null : faturauuid);

                    GetInvoiceRequest getInvoiceRequest = new GetInvoiceRequest();

                    getInvoiceRequest.HEADER_ONLY = (downloadcontent ? "N" : "Y");
                    getInvoiceRequest.INVOICE_CONTENT_TYPE
                        = (contenttype == "XML") ? INVOICE_CONTENT_TYPE.XML
                        : (contenttype == "PDF") ? INVOICE_CONTENT_TYPE.PDF
                        : (contenttype == "ZIP") ? INVOICE_CONTENT_TYPE.ALL
                        : INVOICE_CONTENT_TYPE.XML;


                    getInvoiceRequest.INVOICE_SEARCH_KEY = invoiceSearchKey;
                    getInvoiceRequest.REQUEST_HEADER = _requestHeader;

                    if (direction == "IN")
                    {
                        if (!Directory.Exists(_incomingpath))
                            Directory.CreateDirectory(_incomingpath);
                    }
                    else if (direction == "OUT")
                    {
                        if (!Directory.Exists(_outgoingpath))
                            Directory.CreateDirectory(_outgoingpath);
                    }
                    else
                    {
                        directionPath = _outgoingpath;
                    }


                    //response
                    Stopwatch start = Stopwatch.StartNew();
                    start.Start();
                    INVOICE[] invoiceList = _service.GetInvoice(getInvoiceRequest);
                    start.Stop();
                    MessageBox.Show("GetInvoice Total second:" + start.ElapsedMilliseconds / 1000);

                    foreach (INVOICE invoice in invoiceList)
                    {
                        if (invoice.CONTENT != null)
                        {
                            // download if content exists..
                            string extension = ".xml";
                            if (invoice.CONTENT.contentType.Contains("pdf"))
                            {
                                extension = ".pdf";
                            }
                            else if (invoice.CONTENT.contentType.Contains("zip"))
                            {
                                extension = ".zip";
                            }

                            if (invoice.HEADER != null && invoice.HEADER.DIRECTION != null)
                            {
                                if (invoice.HEADER.DIRECTION.Contains("IN"))
                                {
                                    if (!Directory.Exists(Path.Combine(_incomingpath, "GELEN")))
                                        Directory.CreateDirectory(Path.Combine(_incomingpath, "GELEN"));

                                    directionPath = Path.Combine(_incomingpath, "GELEN");
                                }
                                else
                                {
                                    if (!Directory.Exists(Path.Combine(_outgoingpath, "GIDEN")))
                                        Directory.CreateDirectory(Path.Combine(_outgoingpath, "GIDEN"));

                                    directionPath = Path.Combine(_outgoingpath, "GIDEN");
                                }
                            }
                            else
                            {
                                directionPath = _outgoingpath;
                            }

                            File.WriteAllBytes(Path.Combine(directionPath, invoice.ID + extension), invoice.CONTENT.Value);
                        }
                    }

                    if (invoiceList.Count() > 0)
                        retVal = 0;

                    break;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        /// <summary>
        /// Fatura durum bilgisinin sorgulaması
        /// </summary>
        /// <param name="invoiceNo">16 haneli fatura no</param>
        /// <param name="UUID">ETTN yerine geçen fatura özel no (UUID)</param>
        /// <returns>Fatura durumu açıklamasını döner.</returns>
        public GetInvoiceStatusResponseINVOICE_STATUS CheckInvoiceStatus(string invoiceNoAndUUID, string statusDirection)
        {
            GetInvoiceStatusResponseINVOICE_STATUS retVal = null;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {

                    string request = string.Empty;
                    string response = string.Empty;

                    //request
                    GetInvoiceStatusRequest getInvoiceStatusRequest = new GetInvoiceStatusRequest();
                    getInvoiceStatusRequest.REQUEST_HEADER = _requestHeader;

                    if (invoiceNoAndUUID.Length > 16)
                    {
                        getInvoiceStatusRequest.INVOICE = new INVOICE()
                        {
                            UUID = invoiceNoAndUUID,
                        };
                    }
                    else
                    {
                        getInvoiceStatusRequest.INVOICE = new INVOICE()
                        {
                            ID = invoiceNoAndUUID,
                            //UUID = invoiceNoAndUUID,
                            //HEADER = new INVOICEHEADER()
                            //{
                            //    DIRECTION = "OUT",
                            //    ISACTIVESpecified = true,
                            //    ISACTIVE = true,
                            //}
                        };
                    }


                    //response
                    GetInvoiceStatusResponse getInvoiceStatusResponse = _service.GetInvoiceStatus(getInvoiceStatusRequest);

                    //MessageBox.Show(getInvoiceStatusResponse.INVOICE_STATUS.HEADER);
                    //getInvoiceStatusResponse.INVOICE_STATUS.HEADER.EXPORT_GTB_FIILI_IHRACAT_TARIHI
                    //    getInvoiceStatusResponse.INVOICE_STATUS.HEADER.EXPORT_GTB_FIILI_IHRACAT_TARIHISpecified
                    //    getInvoiceStatusResponse.INVOICE_STATUS.HEADER.EXPORT_GTB_GCB_TESCILNO
                    //    getInvoiceStatusResponse.INVOICE_STATUS.HEADER.EXPORT_GTB_REFNO

                    //return & result

                    return getInvoiceStatusResponse.INVOICE_STATUS;

                    //if (!String.IsNullOrEmpty(getInvoiceStatusResponse.INVOICE_STATUS.UUID))
                    //{
                    //    if (statusDirection == StatusDirection.Entegrator.ToDescription())
                    //        retVal = getPORTALStatusTR(getInvoiceStatusResponse.INVOICE_STATUS.STATUS);
                    //    else if (statusDirection == StatusDirection.GIB.ToDescription())
                    //        retVal = getGIBStatusTR(getInvoiceStatusResponse.INVOICE_STATUS.GIB_STATUS_CODE);
                    //    return retVal;
                    //}
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = null;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = null;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        #endregion


        // okunan faturaların işaretlenmesi  
        #region MarkInvoice
        /// <summary>
        /// Belirlenen faturaların okunmuş olarak işaretlenmesi
        /// </summary>
        /// <param name="invoiceNo">fatura no</param>
        /// <param name="UUID">uuid</param>
        /// <returns>status</returns>
        public int MarkInvoiceRead(string invoiceNo, string UUID = "")
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    MarkInvoiceRequest markInvoiceRequest = new MarkInvoiceRequest();
                    if (String.IsNullOrEmpty(invoiceNo))
                    {
                        markInvoiceRequest = new MarkInvoiceRequest()
                        {
                            REQUEST_HEADER = _requestHeader,
                            MARK = new MarkInvoiceRequestMARK()
                            {
                                valueSpecified = true,
                                value = MarkInvoiceRequestMARKValue.READ,
                                INVOICE = new INVOICE[]
                                         {
                                        new INVOICE()
                                        {
                                           UUID=UUID
                                        },
                                         },
                            }
                        };
                    }
                    else
                    {
                        markInvoiceRequest = new MarkInvoiceRequest()
                        {
                            REQUEST_HEADER = _requestHeader,
                            MARK = new MarkInvoiceRequestMARK()
                            {
                                valueSpecified = true,
                                value = MarkInvoiceRequestMARKValue.READ,
                                INVOICE = new INVOICE[]
                                       {
                                        new INVOICE()
                                        {
                                           ID=invoiceNo
                                        },
                                       },
                            }
                        };
                    }


                    //response
                    MarkInvoiceResponse getInvoiceStatusResponse = _service.MarkInvoice(markInvoiceRequest);

                    //return & result
                    retVal = 0;

                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        /// <summary>
        /// Belirlenen faturaların okunmamış olarak işaretlenmesi
        /// </summary>
        /// <param name="invoiceNo">fatura no</param>
        /// <param name="UUID">uuid</param>
        /// <returns>status</returns>
        public int MarkInvoiceUnRead(string invoiceNo, string UUID = "")
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    MarkInvoiceRequest markInvoiceRequest = new MarkInvoiceRequest();
                    if (String.IsNullOrEmpty(invoiceNo))
                    {
                        markInvoiceRequest = new MarkInvoiceRequest()
                        {
                            REQUEST_HEADER = _requestHeader,
                            MARK = new MarkInvoiceRequestMARK()
                            {
                                valueSpecified = true,
                                value = MarkInvoiceRequestMARKValue.UNREAD,
                                INVOICE = new INVOICE[]
                                         {
                                        new INVOICE()
                                        {
                                           UUID = UUID,
                                        },
                                         },
                            }
                        };
                    }
                    else
                    {
                        markInvoiceRequest = new MarkInvoiceRequest()
                        {
                            REQUEST_HEADER = _requestHeader,
                            MARK = new MarkInvoiceRequestMARK()
                            {
                                valueSpecified = true,
                                value = MarkInvoiceRequestMARKValue.UNREAD,
                                INVOICE = new INVOICE[]
                                       {
                                        new INVOICE()
                                        {
                                             ID = invoiceNo,
                                        },
                                       },
                            }
                        };
                    }


                    //response
                    MarkInvoiceResponse getInvoiceStatusResponse = _service.MarkInvoice(markInvoiceRequest);

                    //return & result
                    retVal = 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        #endregion


        //gelen ticari faturalara yanıt verme
        #region InvoiceResponse(ticari)
        /// <summary>
        /// Gelen ticari faturalar için KABUL EDİLDİ onayı verilmesi
        /// </summary>
        /// <param name="invoiceNo">fatura no</param>
        /// /// <param name="UUID">uuid</param>
        /// <returns>status</returns>
        public int AcceptInvoice(string invoiceNo, string UUID = "")
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    SendInvoiceResponseWithServerSignRequest sendInvoiceResponseWithServerSignRequest
                           = new SendInvoiceResponseWithServerSignRequest();
                    if (invoiceNo.Length > 16)
                    {
                        sendInvoiceResponseWithServerSignRequest
                              = new SendInvoiceResponseWithServerSignRequest()
                              {
                                  REQUEST_HEADER = _requestHeader,
                                  STATUS = "KABUL",
                                  INVOICE = new INVOICE[] {
                                                new INVOICE()
                                                {
                                                    UUID = UUID,
                                                },
                                                 },
                              };
                    }
                    else
                    {
                        sendInvoiceResponseWithServerSignRequest
                           = new SendInvoiceResponseWithServerSignRequest()
                           {
                               REQUEST_HEADER = _requestHeader,
                               STATUS = "KABUL",
                               INVOICE = new INVOICE[] {
                                                new INVOICE()
                                                {
                                                    ID = invoiceNo,
                                                },
                                                  },
                           };
                    }


                    //response
                    SendInvoiceResponseWithServerSignResponse sendInvoiceResponseWithServerSignResponse
                       = _service.SendInvoiceResponseWithServerSign(sendInvoiceResponseWithServerSignRequest);

                    //return & result
                    return 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        /// <summary>
        /// Gelen ticari faturalar için RED EDİLDİ onayı verilmesi
        /// </summary>
        /// <param name="invoiceNo">fatura no</param>
        /// <param name="UUID">uuid</param>
        ///  <returns>status</returns>
        public int RejectInvoice(string invoiceNo, string UUID = "")
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    SendInvoiceResponseWithServerSignRequest sendInvoiceResponseWithServerSignRequest
                           = new SendInvoiceResponseWithServerSignRequest();
                    if (invoiceNo.Length > 16)
                    {
                        sendInvoiceResponseWithServerSignRequest
                          = new SendInvoiceResponseWithServerSignRequest()
                          {
                              REQUEST_HEADER = _requestHeader,
                              STATUS = "RED",
                              DESCRIPTION = new string[] { "TEST CONNECTOR TARAFINDAN RED EDILMISTIR" },
                              INVOICE = new INVOICE[] {
                               new INVOICE()
                               {
                                   UUID = invoiceNo
                               },
                               },
                          };
                    }
                    else
                    {
                        sendInvoiceResponseWithServerSignRequest
                           = new SendInvoiceResponseWithServerSignRequest()
                           {
                               REQUEST_HEADER = _requestHeader,
                               STATUS = "KABUL",
                               INVOICE = new INVOICE[] {
                                                new INVOICE()
                                                {
                                                    ID = invoiceNo,
                                                },
                                                  },
                           };
                    }


                    //response
                    SendInvoiceResponseWithServerSignResponse sendInvoiceResponseWithServerSignResponse
                       = _service.SendInvoiceResponseWithServerSign(sendInvoiceResponseWithServerSignRequest);

                    //return & result
                    return 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;

        }
        #endregion


        //e-arşiv iptal etme
        #region e-Arşiv fatura iptal
        /// <summary>
        /// e-Arşiv faturasını iptale çeker.
        /// </summary>
        /// <param name="invoiceNo">fatura no</param>
        /// <param name="UUID">uuid</param>
        /// <returns>status</returns>
        public int CancelArchiveInvoice(string invoiceNo, string UUID = "")
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    CancelInvoiceRequest cancelInvoiceRequest = new CancelInvoiceRequest();

                    cancelInvoiceRequest.REQUEST_HEADER = _requestHeader;
                    cancelInvoiceRequest.INVOICE = new INVOICE[]
                              {
                                  new INVOICE()
                                  {
                                     UUID = invoiceNo,
                                     //ID=invoiceNo
                                  },
                              };




                    //response
                    CancelInvoiceResponse getInvoiceStatusResponse = _service.CancelInvoice(cancelInvoiceRequest);

                    //return & result
                    retVal = 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        #endregion


        // e-fatura'ya kayıtlı mükellef bilgilerine erişim
        #region GetUserList, CheckUser, GetUserListBinary

        /// <summary>
        /// EDM web servis GİB e-fatura mükellef tam Listesini alma
        /// </summary>
        /// <returns>status</returns>
        public int GetUserList(DateTime registerTime, bool registerTimeSpecified
                               ,DateTime endTime, bool endTimeSpecified
                               ,DateTime removedTime, bool removedTimeSpecified
                               ,bool removedOnly
                               ,string unit
            )
        {
            int retVal = 1;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    GetUserListRequest getUserListRequest = new GetUserListRequest();
                    getUserListRequest.REQUEST_HEADER = _requestHeader;

                    getUserListRequest.REGISTER_TIME_STARTSpecified = registerTimeSpecified;
                    getUserListRequest.REGISTER_TIME_START = registerTime;

                    getUserListRequest.REGISTER_TIME_END = endTime;
                    getUserListRequest.REGISTER_TIME_ENDSpecified = endTimeSpecified;

                    getUserListRequest.REMOVED_ONLYSpecified = removedOnly;
                    getUserListRequest.REMOVED_ONLY = removedOnly;
                    getUserListRequest.REMOVED_TIME_STARTSpecified = removedTimeSpecified;
                    getUserListRequest.REMOVED_TIME_START = removedTime;
                    getUserListRequest.UNIT = unit;

                    //response
                    GetUserListResponse getUserListResponse = _service.GetUserList(getUserListRequest);

                    //return & result
                    GIBUSER[] gibusers = getUserListResponse.Items;
                    //=yalnızca PK ları istenirse.
                    //=GIBUSER[] gibuserPKs = gibusers.Where(t => t.UNIT == "PK").ToArray();

                    CustomEntity.gibUserList = gibusers;

                    retVal = 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        /// <summary>
        /// GİB e-fatura mükllef listesini xml içerik zipli olarak alınır
        /// </summary>
        /// <returns>status</returns>
        public string GetUserListBinary_XML()
        {
            string retVal = string.Empty;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    GetUserListBinaryRequest getUserListBinaryRequest = new GetUserListBinaryRequest();
                    getUserListBinaryRequest.TYPE = GetUserListBinaryRequestTYPE.XML;


                    getUserListBinaryRequest.REQUEST_HEADER = _requestHeader;

                    //response
                    GetUserListBinaryResponse getUserListBinaryResponse = _service.GetUserListBinary(getUserListBinaryRequest);

                    //return & result
                    byte[] userlistdata = getUserListBinaryResponse.Item.Value;

                    File.WriteAllBytes(Path.Combine(@"C:\EDM", "GetUserListBinary_xml.zip"), userlistdata);

                    retVal = "";
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = "";
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = "";
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }
            return retVal;
        }

        /// <summary>
        /// e-fatura mükellef listesi .CSV içeriği şeklinde ve zipli olarak alınır
        /// </summary>
        /// <returns>status</returns>
        public string GetUserListBinary_CSV()
        {
            string retVal = string.Empty;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    GetUserListBinaryRequest getUserListBinaryRequest = new GetUserListBinaryRequest();
                    getUserListBinaryRequest.TYPE = GetUserListBinaryRequestTYPE.CSV;


                    getUserListBinaryRequest.REQUEST_HEADER = _requestHeader;

                    //response
                    GetUserListBinaryResponse getUserListBinaryResponse = _service.GetUserListBinary(getUserListBinaryRequest);

                    //return & result
                    byte[] userlistdata = getUserListBinaryResponse.Item.Value;

                    File.WriteAllBytes(Path.Combine(@"C:\EDM", "GetUserListBinary_csv.zip"), userlistdata);

                    retVal = "";
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = "";
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = "";
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }
            return retVal;
        }

        /// <summary>
        /// Vergi Kimlik No ile e-fatura mükellefi arama
        /// </summary>
        /// <param name="vkn_tckn">vergi no veya tc kimlik no</param>
        /// <returns>status</returns>
        public GIBUSER CheckUser_byIdentifier(string vkn_tckn)
        {
            GIBUSER retVal = null;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    CheckUserRequest checkUserRequest = new CheckUserRequest();

                    checkUserRequest.USER = new GIBUSER();
                    checkUserRequest.REQUEST_HEADER = _requestHeader;
                    checkUserRequest.USER.IDENTIFIER = vkn_tckn;

                    //response
                    GIBUSER[] gibusers = _service.CheckUser(checkUserRequest);

                    //return & result

                    if (gibusers.Length > 0)
                    {
                        retVal = gibusers[0];
                    }
                    else
                    {
                        retVal = null;
                    }

                    return retVal;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = null;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = null;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }

            return retVal;
        }

        /// <summary>
        /// Firma Ünvanı ile  e-fatura mükellefi arama
        /// </summary>
        /// <param name="title">Firma Ünvanı</param>
        /// <returns>status</returns>
        public int CheckUser_byTitle(string title)
        {
            int retVal = 0;

            Exception responseError = null;
            Exception systemError = null;

            for (int z = 1; z <= sessionTryCount; z++)
            {
                try
                {
                    //request
                    CheckUserRequest checkUserRequest = new CheckUserRequest();

                    checkUserRequest.USER = new GIBUSER();
                    checkUserRequest.USER.TITLE = title;
                    checkUserRequest.REQUEST_HEADER = _requestHeader;

                    //response
                    GIBUSER[] gibusers = _service.CheckUser(checkUserRequest);

                    //return & result

                    //vknUser[0].IDENTIFIER.ToString()

                    retVal = 0;
                }
                catch (System.ServiceModel.FaultException<REQUEST_ERRORType> fexp)
                {
                    responseError = fexp;

                    //test
                    CustomEntity.ResponseErrorDetail = xmlSerializeObject((((System.ServiceModel.FaultException<REQUEST_ERRORType>)fexp)).Detail);
                }
                catch (System.ServiceModel.FaultException fex)
                {
                    systemError = fex;
                }
                catch (Exception ex)
                {
                    systemError = ex;
                }

                #region check session abandon & output err msg
                if (responseError != null)
                {
                    //check session abandon
                    if (responseError.Message.Contains(_sessionDestroyedMessage))
                    {
                        responseError = null;
                        EDMGetSession(z, (z == sessionTryCount) ? 1 : 0);//1.parametre:nitelik dışı çağrılma sayısı 2.parametre: oturum denemeleri son bulmuşsa experimentCount 1 olsun
                    }
                    //output response err msg
                    else
                    {
                        retVal = 1;
                        ShowResponseErrMessage(responseError);
                        break;
                    }

                }

                if (systemError != null)
                {
                    //output system err msg
                    if (systemError != null)
                    {
                        retVal = -1;
                        ShowSystemErrMessage(systemError);
                        break;
                    }
                }
                #endregion
            }
            return retVal;
        }

        #endregion


        //kontör sorgulama
        #region CheckCounter

        public void GetCounter()
        {
            try
            {
                CheckCounterRequest checkCounterRequest = new CheckCounterRequest()
                {
                    REQUEST_HEADER = _requestHeader,
                };

                CheckCounterResponse checkCounterResponse = _service.CheckCounter(checkCounterRequest);
                MessageBox.Show(checkCounterResponse.COUNTER_LEFT.ToString());
            }
            catch (Exception exp)
            {
                MessageBox.Show(exp.ToString());
            }

        }

        #endregion


        //yardımcılar
        #region helpers

        public void ShowSystemErrMessage(Exception exp)
        {
            string errMsg = "Hata Kodu: " + "\r" + "Hata Açıklaması: " + exp.ToString();
            MessageBox.Show(errMsg, EDMMessageSystemCaption, MessageBoxButtons.OK, MessageBoxIcon.Error, MessageBoxDefaultButton.Button1);
        }

        public void ShowResponseErrMessage(Exception exp)
        {
            string errMsg = "Uyarı Kodu: " + "\r" + "Uyarı Açıklaması: " + exp.ToString();
            MessageBox.Show(errMsg, EDMMessageResponseCaption, MessageBoxButtons.OK, MessageBoxIcon.Warning, MessageBoxDefaultButton.Button1);
        }

        // yardımcı fonksiyonlar
        #region helper tools

        private string xmlSerializeObject(object Object)
        {

            XmlSerializer serializer = new XmlSerializer(typeof(REQUEST_ERRORType));
            StringWriter SW = new StringWriter();
            serializer.Serialize(SW, Object);
            return SW.ToString();
        }

        protected List<hm.common.Ubltr.Invoice21.InvoiceType> CreateInvoiceTypeTestObject(string serial, int startinvoiceserial, int count)
        {
            List<hm.common.Ubltr.Invoice21.InvoiceType> ublinvoicelist = new List<hm.common.Ubltr.Invoice21.InvoiceType>();


            for (int i = startinvoiceserial; i <= (startinvoiceserial + count); i++)
            {

                // create and return invoice of type InvoiceType
                hm.common.Ubltr.Invoice21.InvoiceType ublinvoice = new hm.common.Ubltr.Invoice21.InvoiceType();

                #region Header
                ublinvoice.UBLExtensions = new hm.common.Ubltr.Invoice21.UBLExtensionType[]
            {
                new hm.common.Ubltr.Invoice21.UBLExtensionType()
                {
                    ExtensionContent = new hm.common.Ubltr.Invoice21.ExtensionContentType()
                    {
                    }
                }
            };

                ublinvoice.UBLVersionID = new hm.common.Ubltr.Invoice21.UBLVersionIDType()
                {
                    Value = "2.1"
                };

                ublinvoice.CustomizationID = new hm.common.Ubltr.Invoice21.CustomizationIDType()
                {
                    Value = "TR1.2"
                };

                ublinvoice.ProfileID = new hm.common.Ubltr.Invoice21.ProfileIDType()
                {
                    Value = "TICARIFATURA"
                };

                ublinvoice.ID = new hm.common.Ubltr.Invoice21.IDType()
                {
                    Value = string.Format("{0}{1}{2}", serial, DateTime.Now.Year, i.ToString().PadLeft(9, '0'))
                };

                ublinvoice.CopyIndicator = new hm.common.Ubltr.Invoice21.CopyIndicatorType()
                {
                    Value = false
                };

                ublinvoice.UUID = new hm.common.Ubltr.Invoice21.UUIDType()
                {
                    Value = Guid.NewGuid().ToString()
                };

                ublinvoice.IssueDate = new hm.common.Ubltr.Invoice21.IssueDateType()
                {
                    Value = DateTime.Now
                };

                ublinvoice.InvoiceTypeCode = new hm.common.Ubltr.Invoice21.InvoiceTypeCodeType()
                {
                    Value = "SATIS"
                };


                List<hm.common.Ubltr.Invoice21.NoteType> notes = new List<hm.common.Ubltr.Invoice21.NoteType>();
                notes.Add(new hm.common.Ubltr.Invoice21.NoteType()
                {
                    Value = "Ticaret Sicil No.:888376, İşletme Merkezi: ISTANBUL"
                });

                ublinvoice.Note = notes.ToArray();

                ublinvoice.DocumentCurrencyCode = new hm.common.Ubltr.Invoice21.DocumentCurrencyCodeType()
                {
                    Value = "TRY"
                };

                #endregion

                #region Signature

                ublinvoice.Signature = new hm.common.Ubltr.Invoice21.SignatureType1[]
            {
                new hm.common.Ubltr.Invoice21.SignatureType1()
                {
                    ID = new hm.common.Ubltr.Invoice21.IDType()
                    {
                        schemeID = "VKN_TCKN",
                        Value = "3230512384"
                    },
                    SignatoryParty = new hm.common.Ubltr.Invoice21.PartyType()
                    {
                        PartyIdentification = new hm.common.Ubltr.Invoice21.PartyIdentificationType[]
                        {
                            new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                            {
                                ID = new hm.common.Ubltr.Invoice21.IDType()
                                {
                                    schemeID = "VKN",
                                    Value = "3230512384"
                                }
                            }
                        },
                        PartyName = new hm.common.Ubltr.Invoice21.PartyNameType()
                        {
                            Name = new hm.common.Ubltr.Invoice21.NameType1()
                            {
                                Value = null
                            },
                        },
                        PartyTaxScheme = new hm.common.Ubltr.Invoice21.PartyTaxSchemeType()
                        {
                            TaxScheme = new hm.common.Ubltr.Invoice21.TaxSchemeType()
                            {
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value = null
                                }
                            }
                        },
                        PostalAddress = new hm.common.Ubltr.Invoice21.AddressType()
                        {
                            StreetName = new hm.common.Ubltr.Invoice21.StreetNameType()
                            {
                                Value = "Cansızoğlu işhanı No:7/35 Mecidiyeköy"
                            },
                            CitySubdivisionName = new hm.common.Ubltr.Invoice21.CitySubdivisionNameType()
                            {
                                Value    = "Şişli"
                            },
                            CityName = new hm.common.Ubltr.Invoice21.CityNameType()
                            {
                                Value = "İstanbul"
                            },
                            Country = new hm.common.Ubltr.Invoice21.CountryType()
                            {
                                IdentificationCode = new hm.common.Ubltr.Invoice21.IdentificationCodeType()
                                {
                                    Value = "TR"
                                },
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value  = "Türkiye"
                                }
                            }
                        }
                    },
                    DigitalSignatureAttachment = new hm.common.Ubltr.Invoice21.AttachmentType()
                    {
                        ExternalReference = new hm.common.Ubltr.Invoice21.ExternalReferenceType()
                        {
                            URI = new hm.common.Ubltr.Invoice21.URIType()
                            {
                                Value =  "#Signature_" + ublinvoice.ID.Value
                            }
                        }
                    }
                }
            };

                #endregion

                #region Default Fatura Dizaynı (xslt)

                ublinvoice.AdditionalDocumentReference = new hm.common.Ubltr.Invoice21.DocumentReferenceType[]
            {
                new hm.common.Ubltr.Invoice21.DocumentReferenceType()
                {
                    ID = new hm.common.Ubltr.Invoice21.IDType()
                    {
                        Value = Guid.NewGuid().ToString()
                    },
                    IssueDate = new hm.common.Ubltr.Invoice21.IssueDateType()
                    {
                        Value = DateTime.Now
                    },
                    Attachment = new hm.common.Ubltr.Invoice21.AttachmentType()
                    {
                        EmbeddedDocumentBinaryObject  = new hm.common.Ubltr.Invoice21.EmbeddedDocumentBinaryObjectType()
                        {
                            filename = ublinvoice.ID.Value + ".xslt",
                            characterSetCode ="UTF-8" ,
                            encodingCode="Base64",
                            mimeCode="application/xml",
                            Value = File.ReadAllBytes("default.xslt")
                        }
                    }
                }

            };


                #endregion

                #region Gönderen Bilgileri

                ublinvoice.AccountingSupplierParty = new hm.common.Ubltr.Invoice21.SupplierPartyType()
                {
                    Party = new hm.common.Ubltr.Invoice21.PartyType()
                    {
                        PartyIdentification = new hm.common.Ubltr.Invoice21.PartyIdentificationType[]
                    {
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "VKN",
                                Value = "3230512384"
                            }
                        },
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "MERSISNO",
                                Value = "0000000000000000"
                            }
                        },
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "TICARETSICILNO",
                                Value = "12345678"
                            }
                        }
                    },
                        PartyName = new hm.common.Ubltr.Invoice21.PartyNameType()
                        {
                            Name = new hm.common.Ubltr.Invoice21.NameType1()
                            {
                                Value = "EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş."
                            }
                        },
                        PostalAddress = new hm.common.Ubltr.Invoice21.AddressType()
                        {
                            StreetName = new hm.common.Ubltr.Invoice21.StreetNameType()
                            {
                                Value = "Cansızoğlu işhanı No:7/35 Mecidiyeköy"
                            },
                            CitySubdivisionName = new hm.common.Ubltr.Invoice21.CitySubdivisionNameType()
                            {
                                Value = "Şişli"
                            },
                            CityName = new hm.common.Ubltr.Invoice21.CityNameType()
                            {
                                Value = "İstanbul"
                            },
                            Country = new hm.common.Ubltr.Invoice21.CountryType()
                            {
                                IdentificationCode = new hm.common.Ubltr.Invoice21.IdentificationCodeType()
                                {
                                    Value = "TR"
                                },
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value = "Türkiye"
                                }
                            }
                        },
                        PartyTaxScheme = new hm.common.Ubltr.Invoice21.PartyTaxSchemeType()
                        {
                            TaxScheme = new hm.common.Ubltr.Invoice21.TaxSchemeType()
                            {
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value = "Büyük Mükellefler"
                                }
                            }
                        },
                        WebsiteURI = new hm.common.Ubltr.Invoice21.WebsiteURIType()
                        {
                            Value = "www.edmbilisim.com.tr"
                        },
                        Contact = new hm.common.Ubltr.Invoice21.ContactType()
                        {
                            Telephone = new hm.common.Ubltr.Invoice21.TelephoneType()
                            {
                                Value = "+90 111 222 3344"
                            }
                        }
                    }
                };

                #endregion

                #region Alıcı Bilgileri

                ublinvoice.AccountingCustomerParty = new hm.common.Ubltr.Invoice21.CustomerPartyType()
                {
                    Party = new hm.common.Ubltr.Invoice21.PartyType()
                    {
                        PartyIdentification = new hm.common.Ubltr.Invoice21.PartyIdentificationType[]
                    {
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "VKN",
                                Value = "3230512384"
                            }
                        },
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "MERSISNO",
                                Value = "0000000000000000"
                            }
                        },
                        new hm.common.Ubltr.Invoice21.PartyIdentificationType()
                        {
                            ID = new hm.common.Ubltr.Invoice21.IDType()
                            {
                                schemeID = "TICARETSICILNO",
                                Value = "12345678"
                            }
                        }
                    },
                        PartyName = new hm.common.Ubltr.Invoice21.PartyNameType()
                        {
                            Name = new hm.common.Ubltr.Invoice21.NameType1()
                            {
                                Value = "EDM Bilişim Sistemleri ve Danışmanlık Hizmetleri A.Ş."
                            }
                        },
                        PostalAddress = new hm.common.Ubltr.Invoice21.AddressType()
                        {
                            StreetName = new hm.common.Ubltr.Invoice21.StreetNameType()
                            {
                                Value = "Cansızoğlu işhanı No:7/35 Mecidiyeköy"
                            },
                            CitySubdivisionName = new hm.common.Ubltr.Invoice21.CitySubdivisionNameType()
                            {
                                Value = "Şişli"
                            },
                            CityName = new hm.common.Ubltr.Invoice21.CityNameType()
                            {
                                Value = "İstanbul"
                            },
                            Country = new hm.common.Ubltr.Invoice21.CountryType()
                            {
                                IdentificationCode = new hm.common.Ubltr.Invoice21.IdentificationCodeType()
                                {
                                    Value = "TR"
                                },
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value = "Türkiye"
                                }
                            }
                        },
                        PartyTaxScheme = new hm.common.Ubltr.Invoice21.PartyTaxSchemeType()
                        {
                            TaxScheme = new hm.common.Ubltr.Invoice21.TaxSchemeType()
                            {
                                Name = new hm.common.Ubltr.Invoice21.NameType1()
                                {
                                    Value = "Büyük Mükellefler"
                                }
                            }
                        },
                        WebsiteURI = new hm.common.Ubltr.Invoice21.WebsiteURIType()
                        {
                            Value = "www.edmbilisim.com.tr"
                        },
                        Contact = new hm.common.Ubltr.Invoice21.ContactType()
                        {
                            Telephone = new hm.common.Ubltr.Invoice21.TelephoneType()
                            {
                                Value = "+90 111 222 3344"
                            }
                        }
                    }
                };

                #endregion

                #region Total Taxes

                ublinvoice.TaxTotal = new hm.common.Ubltr.Invoice21.TaxTotalType[]
            {
                new hm.common.Ubltr.Invoice21.TaxTotalType()
                {
                    TaxAmount = new hm.common.Ubltr.Invoice21.TaxAmountType()
                    {
                        currencyID = "TRY",
                        Value = 3600
                    },
                    TaxSubtotal = new hm.common.Ubltr.Invoice21.TaxSubtotalType[]
                    {
                        new hm.common.Ubltr.Invoice21.TaxSubtotalType()
                        {
                            TaxableAmount = new hm.common.Ubltr.Invoice21.TaxableAmountType()
                            {
                                currencyID = "TRY",
                                Value = 20000
                            },
                            TaxAmount = new hm.common.Ubltr.Invoice21.TaxAmountType()
                            {
                                currencyID = "TRY",
                                Value = 3600
                            },
                            CalculationSequenceNumeric = new hm.common.Ubltr.Invoice21.CalculationSequenceNumericType()
                            {
                                Value = 1
                            },
                            Percent = new hm.common.Ubltr.Invoice21.PercentType1()
                            {
                                Value = 18
                            },
                            TaxCategory = new hm.common.Ubltr.Invoice21.TaxCategoryType()
                            {
                                TaxScheme = new hm.common.Ubltr.Invoice21.TaxSchemeType()
                                {
                                    Name = new hm.common.Ubltr.Invoice21.NameType1()
                                    {
                                        Value = "KDV GERCEK"
                                    },
                                    TaxTypeCode = new hm.common.Ubltr.Invoice21.TaxTypeCodeType()
                                    {
                                        Value = "0015"
                                    }
                                }
                            }
                        }
                    }
                }
            };


                #endregion

                #region Invoice.LegalMonetaryTotal

                ublinvoice.LegalMonetaryTotal = new hm.common.Ubltr.Invoice21.MonetaryTotalType()
                {
                    LineExtensionAmount = new hm.common.Ubltr.Invoice21.LineExtensionAmountType()
                    {
                        currencyID = "TRY",
                        Value = 20000
                    },
                    TaxExclusiveAmount = new hm.common.Ubltr.Invoice21.TaxExclusiveAmountType()
                    {
                        currencyID = "TRY",
                        Value = 20000
                    },
                    TaxInclusiveAmount = new hm.common.Ubltr.Invoice21.TaxInclusiveAmountType()
                    {
                        currencyID = "TRY",
                        Value = 23600
                    },
                    AllowanceTotalAmount = new hm.common.Ubltr.Invoice21.AllowanceTotalAmountType()
                    {
                        currencyID = "TRY",
                        Value = 0
                    },
                    PayableAmount = new hm.common.Ubltr.Invoice21.PayableAmountType()
                    {
                        currencyID = "TRY",
                        Value = 23600
                    },
                };

                #endregion

                #region invoice lines


                List<hm.common.Ubltr.Invoice21.InvoiceLineType> invoiceLines
                    = new List<hm.common.Ubltr.Invoice21.InvoiceLineType>();

                hm.common.Ubltr.Invoice21.InvoiceLineType invoiceLine
                    = new hm.common.Ubltr.Invoice21.InvoiceLineType()
                    {
                        ID = new hm.common.Ubltr.Invoice21.IDType()
                        {
                            Value = "1"
                        },
                        InvoicedQuantity = new hm.common.Ubltr.Invoice21.InvoicedQuantityType()
                        {
                            unitCode = "NIU",
                            Value = 1
                        },
                        LineExtensionAmount = new hm.common.Ubltr.Invoice21.LineExtensionAmountType()
                        {
                            currencyID = "TRY",
                            Value = 20000
                        },
                        TaxTotal = new hm.common.Ubltr.Invoice21.TaxTotalType()
                        {
                            TaxAmount = new hm.common.Ubltr.Invoice21.TaxAmountType()
                            {
                                currencyID = "TRY",
                                Value = 3600
                            },
                            TaxSubtotal = new hm.common.Ubltr.Invoice21.TaxSubtotalType[]
                        {
                            new hm.common.Ubltr.Invoice21.TaxSubtotalType()
                            {
                                TaxableAmount = new hm.common.Ubltr.Invoice21.TaxableAmountType()
                                {
                                    currencyID = "TRY",
                                    Value = 20000
                                },
                                TaxAmount = new hm.common.Ubltr.Invoice21.TaxAmountType()
                                {
                                    currencyID = "TRY",
                                    Value = 3600
                                },
                                CalculationSequenceNumeric = new hm.common.Ubltr.Invoice21.CalculationSequenceNumericType()
                                {
                                    Value = 1
                                },
                                Percent = new hm.common.Ubltr.Invoice21.PercentType1()
                                {
                                    Value = 18
                                },
                                TaxCategory = new hm.common.Ubltr.Invoice21.TaxCategoryType()
                                {
                                    TaxScheme = new hm.common.Ubltr.Invoice21.TaxSchemeType()
                                    {
                                        Name = new hm.common.Ubltr.Invoice21.NameType1()
                                        {
                                            Value = "KDV GERCEK"
                                        },
                                        TaxTypeCode = new hm.common.Ubltr.Invoice21.TaxTypeCodeType()
                                        {
                                            Value = "0015"
                                        }
                                    }
                                }
                            }
                        }
                        },
                        Item = new hm.common.Ubltr.Invoice21.ItemType()
                        {
                            Name = new hm.common.Ubltr.Invoice21.NameType1()
                            {
                                Value = "Ürün Bedeli"
                            }
                        },
                        Price = new hm.common.Ubltr.Invoice21.PriceType()
                        {
                            PriceAmount = new hm.common.Ubltr.Invoice21.PriceAmountType()
                            {
                                currencyID = "TRY",
                                Value = 20000
                            }
                        }
                    };

                invoiceLines.Add(invoiceLine);


                ublinvoice.InvoiceLine = invoiceLines.ToArray();

                ublinvoice.LineCountNumeric = new hm.common.Ubltr.Invoice21.LineCountNumericType()
                {
                    Value = invoiceLines.Count
                };

                #endregion

                ublinvoicelist.Add(ublinvoice);
            }

            return ublinvoicelist;
        }

        //response süresi ölçme
        public static object[] Measure<TI, TO>(Func<TI, TO> action, TI o)
        {
            System.Diagnostics.Stopwatch sw = new System.Diagnostics.Stopwatch();
            sw.Start();
            var output = (TO)action(o);
            sw.Stop();

            return new object[] { output, sw.ElapsedMilliseconds, action.Method.Name };
        }



        #endregion

        #endregion


        //code status behaviors
        #region code status&description
        
        /// <summary>
        /// Sadece client tarafında numaralandırılmıştır. Servis tarafındaki kodları yansıtmaz 
        /// </summary>
        /// <param name="inputValue"></param>
        /// <returns></returns>
        private string GetPORTALStatusTR(string inputValue)
        {
            Dictionary<string, string> getCodeAndReasons = CurrentEDMConnectorClientLibrary.Consts.EDMservicemsg._getMessageDict;
            string retMsg = string.Empty;
            string concatPattern = "{0} - {1}";

            switch (inputValue)
            {
                case "PACKAGE - PROCESSING":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "101").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "101").FirstOrDefault().Value);
                    break;
                case "SEND - PROCESSING":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "102").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "102").FirstOrDefault().Value);
                    break;
                case "SEND - WAIT_GIB_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "103").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "103").FirstOrDefault().Value);
                    break;
                case "SEND - WAIT_SYSTEM_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "104").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "104").FirstOrDefault().Value);
                    break;
                case "UNKNOWN - UNKNOWN":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "105").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "105").FirstOrDefault().Value);
                    break;
                case "SEND - WAIT_APPLICATION_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "106").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "106").FirstOrDefault().Value);
                    break;
                case "RECEIVE - WAIT_SYSTEM_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "107").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "107").FirstOrDefault().Value);
                    break;
                case "RECEIVE - WAIT_APPLICATION_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "108").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "108").FirstOrDefault().Value);
                    break;
                case "ACCEPT - PROCESSING":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "109").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "109").FirstOrDefault().Value);
                    break;
                case "ACCEPT - WAIT_GIB_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "110").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "110").FirstOrDefault().Value);
                    break;
                case "ACCEPT - WAIT_SYSTEM_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "111").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "111").FirstOrDefault().Value);
                    break;
                case "REJECT - PROCESSING":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "112").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "112").FirstOrDefault().Value);
                    break;
                case "REJECT - WAIT_GIB_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "113").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "113").FirstOrDefault().Value);
                    break;
                case "REJECT - WAIT_SYSTEM_RESPONSE":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "114").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "114").FirstOrDefault().Value);
                    break;
                case "SEND - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "201").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "201").FirstOrDefault().Value);
                    break;
                case "ACCEPTED - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "202").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "202").FirstOrDefault().Value);
                    break;
                case "REJECTED - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "203").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "203").FirstOrDefault().Value);
                    break;
                case "CANCELLED - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "204").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "204").FirstOrDefault().Value);
                    break;
                case "RECEIVE - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "205").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "205").FirstOrDefault().Value);
                    break;
                case "ACCEPT - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "206").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "206").FirstOrDefault().Value);
                    break;
                case "REJECT - SUCCEED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "207").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "206").FirstOrDefault().Value);
                    break;
                case "PACKAGE - FAIL":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "301").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "301").FirstOrDefault().Value);
                    break;
                case "SEND - FAILED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "302").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "302").FirstOrDefault().Value);
                    break;
                case "ACCEPT - FAILED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "303").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "303").FirstOrDefault().Value);
                    break;
                case "REJECT - FAILED":
                    retMsg = string.Format(concatPattern, getCodeAndReasons.Where(t => t.Key == "304").FirstOrDefault().Key, getCodeAndReasons.Where(t => t.Key == "304").FirstOrDefault().Value);
                    break;

                default:
                    retMsg = string.Empty;
                    break;
            }

            return retMsg;
        }

        private string GetGIBStatusTR(int inputValue)
        {
            Dictionary<string, string> getCodeAndReasons = CurrentEDMConnectorClientLibrary.Consts.GIBservicemsg._getMessageDict;
            string retMsg = string.Empty;

            string parameter = inputValue.ToString();
            retMsg = getCodeAndReasons.Where(t => t.Key == parameter).FirstOrDefault().Value;

            return retMsg;
        }

        #endregion


    }

}