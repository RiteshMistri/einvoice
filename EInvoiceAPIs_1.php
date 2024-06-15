<?php

use Mpdf\Utils\Arrays;

include_once('../config.php');
include_once('../cnn_mysqli.php');
include_once('../functions_php.php');
include_once('SiyaConfig.php');
include 'ewaybill/function.php';
class EInvoiceAPIs extends SiyaConfig{

    protected $cnn;
    protected $sub_key;
    protected $app_key;
    protected $transfer;
    protected $publicKey;
    protected $errorList;
    protected $BranchDetail;
    protected $Mode;

    public $msg;
    public function __construct($cnn){
        include 'ewaybill/errorlist.php';
        parent::__construct($cnn);
        $this->cnn = $cnn;
        $this->errorList = $errorarr;
        $this->Mode = 'Dev';
        $this->msg = '';
        $this->BranchDetail = $this->getBranchDetail();
        $this->sub_key = 'AL9r5j2k7j1y8A6m1R';
        $this->app_key = "bknA4HE1N+7uwckEySYsrGmO8qbp1NukmkXuzO35ifC/vXbWVuysNKZobvP7NmxOtuyUSlIpRQIKVU0dcnFS416SznoSNydxiP/X7ISyajyM3wbn3iK8NV5LGqCeXa7k1Fm2CTcNxoU3bMt+vNw9ah433agurG090UMSG9/UxSGjMe+tFQkM03GPCuMtxGSkF+o0Cps+sT5roL4V9rWeWPR1KOrfFgHiTDX8ub1LTQXbUF3YKniZUM8mhHCWvl90cIToF9bMw8TMN7FElila3cbT7LH88SkjDo8RY+lsYKvYB4Q1bQn5auTXgvqbHLvgj/jSZit04p1iUGKCPGDP+A==";
        $this->publicKey =  file_get_contents('einv_sandbox.pem');
        if($this->Mode != 'Dev'){
            $this->publicKey =  file_get_contents('einv_production.pem');
        }
    }
    public function auth($input){
        $userData = [
            'UserName' => 'AL001',
            'Password'=>'Alankit@123'
        ];
        $url = "https://gstsandbox.charteredinfo.com/eivital/dec/v1.04/auth";
        if($this->Mode != 'Dev'){
            $url = "https://gstsandbox.charteredinfo.com/eivital/dec/v1.04/auth";
        }
        $userData = $this->GetBranchGspCredentials();

        // error_reporting(E_ALL);
        // openssl_public_encrypt($userData['Password'], $encrypted, $this->publicKey);
        // $this->transfer = base64_encode($encrypted);
        // $obj = new stdClass();
        // $obj->UserName = $userData['UserName'];
        // $obj->Password = $userData['Password'];
        // $obj->AppKey = 'E9Vf1xYcMxKhukeuEJkpX6YGFQaaKfnjyFCMF9czwgM=';
        // $obj->ForceRefreshAccessToken =true;
        // // $postData = array(
        // //     'UserName' => $userData['UserName'],
        // //     'Password' => $userData['Password'],
        // //     'AppKey' =>'E9Vf1xYcMxKhukeuEJkpX6YGFQaaKfnjyFCMF9czwgM=',
        // //     "ForceRefreshAccessToken"=>true,
        // // );
        // $postData = $obj;
        // $postData = json_encode($postData);
        // $enData = base64_encode($postData);
        // openssl_public_encrypt($enData, $encrypted, $this->publicKey);
        // $this->transfer = base64_encode($encrypted);
        // $obj = new stdClass();
        // $obj->Data =  $this->transfer;
        // $DeliveryAddressDetaiFdata = json_encode($obj);

        // Parameters to be sent in the URL
        $params = [
            'aspid' => '1763812424',
            'password' => 'P@ssw0rd',
            'Gstin' => '34AACCC1596Q002',
            'User_name' => $userData['UserName'],
            'eInvPwd' => $userData['Password']
        ];

        $header = [
            'aspid' => '1763812424',
            'password' => 'P@ssw0rd',
        ];
        // Append the parameters to the URL
        $url .= '?' . http_build_query($params);

        // $ch = curl_init($url);
        // curl_setopt_array($ch, array(
        //     CURLOPT_POST => true,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_HTTPHEADER => array(
        //         'Ocp-Apim-Subscription-Key:' . $this->sub_key,
        //         'Gstin:'.$userData['GSTN']
        //     ),
        //     CURLOPT_POSTFIELDS => $DeliveryAddressDetaiFdata,
        // ));
        // $response = curl_exec($ch);

        // Initialize cURL session
        $ch = curl_init();
        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL certificate verification (not recommended for production)
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        // Execute cURL session and get the response
        $response = curl_exec($ch);

        $info =  json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($info['Status'] == 0) {
            // $info = json_decode(base64_decode($info['ErrorDetails']));
            $info = $info['ErrorDetails'];
            $errMsg = array_column($info,'ErrorMessage');
            $errMsg = implode(',',$errMsg);
            InsertSS_ApiLog(0, '', '', 'ACCESSTOKEN', "false", $errMsg, $this->appcode, $this->AdminId);
            return ['status'=>0,'data'=>$info,'postData'=>$postData,'userData'=>$userData,'$errMsg'=>$errMsg];
        } elseif ($info != '') {
            $status = $info['Status'];
            $info = $info['Data'];
            $msg = "Authenticate Successfully";
            $authtoken = $info['AuthToken']; 
            $sek = $info['Sek'];
            $query = "UPDATE TA_GspEInvoiceCredential set AuthToken='$authtoken',Sek='$sek' where BranchID = ".$_SESSION['BranchID'];
            mysqli_query($this->cnn, $query);
            InsertSS_ApiLog($status, $authtoken, $sek, 'ACCESSTOKEN', "true", 'Success', $this->appcode, $this->AdminId);
            return ['status'=>1,'data'=>$info,'q'=>$query];
        }else{
            return [
                'status' => 0,
                'message' =>'Missing parameters.'
            ];
        }
    }
    public function GetBranchGspCredentials(){
        $sql = "Select GSP.UserName,GSP.Password,BMst.GSTN,GSP.AuthToken,Sek from TA_GspEInvoiceCredential as GSP 
        Left join TA_BranchMst as BMst On GSP.GSPID = '1' and GSP.BranchID = BMst.BranchID where GSP.BranchID =" . $this->BranchID;
        return $this->getSingleData($sql);
    }
    public function getBranchDetail($BranchID = 0){
        if($BranchID == 0 ){
            $BranchID = $this->BranchID;
        }
        $sql = "SELECT * from TA_BranchMst WHERE BranchID = '$BranchID'";
        return $this->getSingleData($sql);
    }
    public function getCompanyMaster(){
        $sql = "SELECT * from TA_CompanyMaster WHERE companyId = '$this->CompanyCode'";
        return $this->getSingleData($sql);
    }
    public function GenerateReturnIRN($input){
        if(empty($input['PKID']) && intval($input['PKID']) <= 0){
            return ['status'=>0,'Message'=>'PKID Require'];
        }
        $Book = 'SalesReturn';
        if(!empty($input['Book'])){
            $Book = $input['Book'];
        }
        $PKID = intval($input['PKID']);
        if(in_array($Book,['SalesCreditNote','SalesDebitNote'])){
            $invoiceData =  $this->getSalesCrDrNoteDetailForIRN($PKID,$Book);
        }elseif( $Book == 'SalesReturn'){
            $invoiceData =  $this->getSalesReturnDetailForIRN($PKID);
        }elseif( $Book == 'SalesJournal'){
            $invoiceData =  $this->getSalesJournalInvoiceDetailForIRN($PKID);
        }else {
            return ['status'=>0,'E-Invoice Not Available'];
        }
        $userData = [
            'UserName' => 'AL001'
        ];
        $url = "https://developers.eraahi.com/eInvoiceGateway/eicore/v1.03/Invoice";
        if($this->Mode != 'Dev'){
            $url = 'https://temp.alankit.com/eInvoiceGateway/eicore/v1.03/Invoice';
            $userData = $this->GetBranchGspCredentials();
        }  
        $data =  $this->data_encrypt($userData['Sek'],$invoiceData);
        $header =   array(
            'user_name:'. $userData['UserName'],
            'Gstin:'. $userData['GSTN'],
            'AuthToken:'.$userData['AuthToken'],
            'Ocp-Apim-Subscription-Key:' . $this->sub_key
        );
        $data = json_encode(['Data'=>$data]);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>$header,
            CURLOPT_POSTFIELDS => $data,
        ));
        $response = curl_exec($ch);
        $EInvoiceResponseData = [];
        $$Edata = [];
        $$eivID = 0;
        $response = (Array) json_decode($response);
        if(!empty($response['Status']) && $response['Status'] == 1){
            $EInvoiceResponseData = (Array)  $this->data_decrypt($userData['Sek'],$response['Data']);
            $Edata = [];
            $Edata['TableID'] = $PKID;
            $Edata['AckNo'] = $EInvoiceResponseData['AckNo'];
            $Edata['Book'] = $Book;
            $Edata['AckDt'] = $EInvoiceResponseData['AckDt'];
            $Edata['Irn'] = $EInvoiceResponseData['Irn'];
            $Edata['SignedInvoice'] = mysqli_real_escape_string($this->cnn,$EInvoiceResponseData['SignedInvoice']);
            $Edata['SignedQRCode'] =  mysqli_real_escape_string($this->cnn,$EInvoiceResponseData['SignedQRCode']);
            $Edata['EwbNo'] = $EInvoiceResponseData['EwbNo'];
            $Edata['EwbDt'] = $EInvoiceResponseData['EwbDt'];
            $Edata['CompanyCode'] = $this->CompanyCode;
            $Edata['EwbValidTill'] = $EInvoiceResponseData['EwbValidTill'];
            $Edata['Status'] = $EInvoiceResponseData['Status'];
            $eivID = $this->insertData('TA_ECrDrNoteDetail',$Edata);
        }else{  
            $ErrorDetails = (Array) $response['ErrorDetails'];
            $errMsg = " <br>";
            foreach ($ErrorDetails as $key => $eror) {
                $eror1 = (Array)$eror;
                if($eror1["ErrorCode"] == "1005"){
                    $this->auth($input);
                    return $this->GenerateReturnIRN($input);
                }
                // There was a bug in Database Tale that Primary not set to Auto increment, due to this data not insertedin pwp Database but EInvoice was created at GST Server

                // In that case we will get E-Ivoice detail by Irn no & Insert data to pwp Database
                
                if($eror1["ErrorCode"] == "2150"){
                    $InfoDtls = (Array) $response['InfoDtls'];
                    $DuplicateIrnDetail = new stdClass();
                    foreach($InfoDtls as $info) {
                        if($info->InfCd == "DUPIRN"){
                            $DuplicateIrnDetail = $info->Desc;
                            break;
                        }
                    }

                    if (is_object($DuplicateIrnDetail)) {
                        $url = "https://developers.eraahi.com/eInvoiceGateway/eicore/v1.03/Invoice/irn/".$DuplicateIrnDetail->Irn;
                        if($this->Mode != 'Dev'){
                            $url = 'https://temp.alankit.com/eInvoiceGateway/eicore/v1.03/Invoice/irn/'.$DuplicateIrnDetail->Irn;
                        }  

                        $ch = curl_init($url);
                        curl_setopt_array($ch, array(
                            CURLOPT_HTTPGET => true,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER =>$header,
                        ));
                        $resp = curl_exec($ch);
                        $resp = (Array) json_decode($resp);

                        if(!empty($resp['Status']) && $resp['Status'] == 1){
                            $IRNDetails = (Array)  $this->data_decrypt($userData['Sek'],$resp['Data']);

                            $Edata = [];
                            $Edata['TableID'] = $PKID;
                            $Edata['AckNo'] = $IRNDetails['AckNo'];
                            $Edata['Book'] = $Book;
                            $Edata['AckDt'] = $IRNDetails['AckDt'];
                            $Edata['Irn'] = $IRNDetails['Irn'];
                            $Edata['SignedInvoice'] = mysqli_real_escape_string($this->cnn,$IRNDetails['SignedInvoice']);
                            $Edata['SignedQRCode'] =  mysqli_real_escape_string($this->cnn,$IRNDetails['SignedQRCode']);
                            $Edata['EwbNo'] = $IRNDetails['EwbNo'];
                            $Edata['EwbDt'] = $IRNDetails['EwbDt'];
                            $Edata['CompanyCode'] = $this->CompanyCode;
                            $Edata['EwbValidTill'] = $IRNDetails['EwbValidTill'];
                            $Edata['Status'] = $IRNDetails['Status'];
                            
                            $eivID = $this->insertData('TA_ECrDrNoteDetail',$Edata);
                        }
                    }
                }
                $errMsg .= " ( ".$eror1["ErrorCode"]." ) - ".$eror1["ErrorMessage"]." <br>";
            }
            return ["Message" =>$errMsg, "status" =>0,'response'=>$response,'invoiceData'=>$invoiceData,'ErrorCode'=>$eror1["ErrorCode"]];
        }
        return [
            'RequestData' => [
                'response'=>$response,
                'header'=>$header,
                'data'=>$data,
                'invoiceData'=>$invoiceData,
                'Edata'=>$Edata,
                'eivID'=>$eivID,
                'EInvoiceResponseData'=>$EInvoiceResponseData,
                'invoiceData'=>$invoiceData,
                'userData'=>$userData
            ]
        ];
    }
    
    public function getSalesCrDrNoteDetailForIRN($InvoiceId,$Book){
        if(intval($InvoiceId) > 0){
            $BranchData = $this->getBranchDetail();
            $CompanyData = $this->getCompanyMaster();
            $sql = "select * from TA_CreditNote where JournalInvoiceID = '$InvoiceId' and Book='$Book' 
            and RecordName='Buyer' and CompanyCode = '$this->CompanyCode' and IsDeleted=0;";
            $invoiceData = $this->getSingleData($sql);
            $sql = "select * from TA_CreditNote where JournalInvoiceID = '$InvoiceId' and Book='$Book' 
            and RecordName='Party' and CompanyCode = '$this->CompanyCode'  and IsDeleted=0";
            $invoiceDetailData = $this->getMultiData($sql);
            $InvoiceIRNJSONData = [];
            $InvoiceIRNJSONData['Version'] = '1.1';
            if(!empty($invoiceData)){
                $BuyerData = $this->getPartyInfo($invoiceData['PartyID']);
                $BuyerAddressData = $this->getAddressByPartyID($invoiceData['PartyID']);
                $BuyerDtls = [];
                $BuyerDtls['Gstin'] = (!empty($BuyerData['GSTN'])) ? $BuyerData['GSTN'] : '';
                $BuyerDtls['LglNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['TrdNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['Pos'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Supply
                $BuyerDtls['Stcd'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Buyer
                $BuyerDtls['Addr1'] = (!empty($BuyerAddressData['Address'])) ? substr($BuyerAddressData['Address'],0,99) : '';
                if((!empty($BuyerAddressData['Address2']))){
                    $BuyerDtls['Addr2'] = $BuyerAddressData['Address2'];
                }
                $BuyerDtls['Loc'] = (!empty($BuyerAddressData['Address3'])) ? $BuyerAddressData['Address3'] : '';
                $BuyerDtls['Pin'] = (!empty($BuyerAddressData['Pincode'])) ? intval($BuyerAddressData['Pincode']) : '';//Pin Code
                $InvoiceIRNJSONData['BuyerDtls'] = $BuyerDtls;
            }
            if(!empty($BranchData)){
                $SellerDtls = [];
                $SellerDtls['Gstin'] = (!empty($BranchData['GSTN'])) ? $BranchData['GSTN'] : '';
                $SellerDtls['LglNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['TrdNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['Stcd'] = (!empty($BranchData['BranchState'])) ? $BranchData['BranchState'] : '';//State Code Buyer
                $SellerDtls['Addr1'] = (!empty($BranchData['BranchAddress'])) ? substr($BranchData['BranchAddress'],0,99) : '';
                $SellerDtls['Loc'] = (!empty($BranchData['City'])) ? $BranchData['City'] : '';
                $SellerDtls['Pin'] = (!empty($BranchData['PinCode'])) ? intval($BranchData['PinCode']) : '';//Pin Code
                $SellerDtls['Ph'] =  (!empty($BranchData['Contact1'])) ? str_replace('-','',trim(explode(',',$BranchData['Contact1'])[0])) : '';
                $InvoiceIRNJSONData['SellerDtls'] = $SellerDtls;
            }
            if(!empty($invoiceData['DispFromBranchID']) && intval($invoiceData['DispFromBranchID']) > 0 && $invoiceData['DispFromBranchID'] != $$BranchData['BranchID']){
                $DisBranchData = $this->getBranchDetail($invoiceData['DispFromBranchID']);
                $DispDtls = [];
                $DispDtls['Nm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $DispDtls['Stcd'] = (!empty($DisBranchData['BranchState'])) ? $DisBranchData['BranchState'] : '';//State Code Buyer
                $DispDtls['Addr1'] = (!empty($DisBranchData['BranchAddress'])) ? substr($DisBranchData['BranchAddress'],0,99) : '';
                $DispDtls['Loc'] = (!empty($DisBranchData['City'])) ? $DisBranchData['City'] : '';
                $DispDtls['Pin'] = (!empty($DisBranchData['PinCode'])) ? intval($DisBranchData['PinCode']) : '';//Pin Code
                $InvoiceIRNJSONData['DispDtls'] = $DispDtls;
            }
            $ItemList = [];
            $GSTRates = $this->getGSTRates();
            $i= 1;
            foreach ($invoiceDetailData as $key => $itemData) {
                $invItem = [];
                $PartyData = $this->getPartyInfo($itemData['PartyID']);
                $Srno = $i++;
                $invItem['SlNo'] = "$Srno" ;
                $invItem['IsServc'] = 'N';
                $invItem['HsnCd'] = $itemData['HSN'];
                $invItem['PrdDesc'] = $PartyData['Name'];
                $invItem['Qty'] = floatval(($itemData['RatePer'] == 0)? $itemData['Unit'] : $itemData['Qty']);
                $invItem['Unit'] = 'UNT';
                $invItem['UnitPrice'] = floatval($itemData['Rate']);
                
                $invItem['TotAmt'] = floatval($itemData['SubTotal'] );
                $invItem['Discount'] = floatval($itemData['Discount']);
                
                $invItem['PreTaxVal'] = floatval($itemData['Taxable']);

                $invItem['IgstAmt'] = floatval($itemData['IGST']);
                $invItem['CgstAmt'] = floatval($itemData['CGST']);
                $invItem['SgstAmt'] = floatval($itemData['SGST']);
                
                $invItem['AssAmt'] = floatval($itemData['Taxable']);
                $invItem['GstRt'] = floatval($GSTRates[$itemData['GSTID']]['GST']);
                $TotalValue = floatval($invItem['AssAmt']  + $invItem['SgstAmt'] + $invItem['IgstAmt'] + $invItem['CgstAmt']);
                $invItem['TotItemVal'] = round($TotalValue,2); // Not found in Total
                $ItemList[] =$invItem;
            }
            $InvoiceIRNJSONData['ItemList'] = $ItemList;
            $InvoiceIRNJSONData['ItemListSql'] = $sql;
            // $InvoiceIRNJSONData['invoiceDetailData'] = $invoiceDetailData;
            $TranDtls = [];
            $TranDtls['TaxSch'] = 'GST';
            $TranDtls['SupTyp'] = 'B2B';
            $InvoiceIRNJSONData['TranDtls'] = $TranDtls;
            $ValDtls = [];
            $ValDtls['CgstVal'] = floatval($invoiceData['CGST']);
            $ValDtls['SgstVal'] = floatval($invoiceData['SGST']);
            $ValDtls['IgstVal'] = floatval($invoiceData['IGST']);
            $ValDtls['AssVal'] = floatval($invoiceData['Taxable']);
            $invoiceData['DiscountAmount'] = 0;
            $ValDtls['Discount'] = floatval($invoiceData['DiscountAmount']);
            $ValDtls['RndOffAmt'] = floatval($invoiceData['Roundoff']);
            $ValDtls['TotInvVal'] = floatval($invoiceData['Total']);
            $InvoiceIRNJSONData['ValDtls'] = $ValDtls;
            if(!empty($invoiceData)){
                $DocDtls =[];
                $INvoiceNo = '';
                if(!empty($invoiceData['Prefix'])){
                    $INvoiceNo .= $invoiceData['Prefix'];
                }
                $INvoiceNo .= $invoiceData['InvoiceNo'];
                if($Book == 'SalesCreditNote'){
                    $DocDtls['Typ']='CRN'; //"CRN","DBN"
                }else{
                    $DocDtls['Typ']='DBN'; //"CRN","DBN"
                }
                $DocDtls['No']=$INvoiceNo;
                $DocDtls['Dt']=date_format(date_create($invoiceData['InvoiceDate']),"d/m/Y");
                $InvoiceIRNJSONData['DocDtls'] = $DocDtls;
            }
            $AddressType = $invoiceData['sd_AddressType'];
            $DeliveryAddressDetail = [];
            $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['PartyID']);           
            if (!empty($invoiceData['HasteID'])) {
                $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['HasteID']);
                if(!empty($DeliveryAddressDetail)){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($invoiceData['HasteName'])) ? $invoiceData['HasteName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($invoiceData['HasteName'])) ? $DeliveryAddressDetail['HasteName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }else if(!empty($invoiceData['PrintPartyAddress'])  && $invoiceData['PrintPartyAddress'] != $DeliveryAddressDetail['AddressID']){
                $DeliveryAddressDetail = $this->getAddressByID($invoiceData['PrintPartyAddress']);
                if(!empty($DeliveryAddressDetail) && !empty($DeliveryAddressDetail['AliasName'])){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }
            // if(!empty($invoiceData['sd_distance'])){
                $EwbDtls = [];
                if(!empty($invoiceData['TransporterGSTN'])){
                    $EwbDtls['TransId'] = $invoiceData['TransporterGSTN'];
                }
                $EwbDtls['Distance'] = 0;
                if(!empty($invoiceData['sd_distance']) && intval($invoiceData['sd_distance']) > 0){
                    $EwbDtls['Distance'] = intval($invoiceData['sd_distance']);
                }   
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['TransporterName'])){
                    $EwbDtls['TransName'] = $invoiceData['TransporterName'];
                }else if(!empty($invoiceData['so_Transporter'])){
                    $EwbDtls['TransName'] = $invoiceData['so_Transporter'];
                }
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['sd_vehicleType'])){
                    $EwbDtls['VehType'] = $invoiceData['sd_vehicleType'];
                }
                if(!empty($invoiceData['sd_transMode'])){
                    $EwbDtls['TransMode'] = $invoiceData['sd_transMode'];
                }
                $InvoiceIRNJSONData['EwbDtls'] = $EwbDtls;
            // }
        }
        return $InvoiceIRNJSONData;
    }
    
    public function getSalesJournalInvoiceDetailForIRN($InvoiceId){
        if(intval($InvoiceId) > 0){
            $BranchData = $this->getBranchDetail();
            $CompanyData = $this->getCompanyMaster();
            $sql = "select * from TA_SalesJournalInvoice where JournalInvoiceID = '$InvoiceId' and Book='Buyer' 
             and CompanyCode = '$this->CompanyCode' and IsDeleted=0;";
            $invoiceData = $this->getSingleData($sql);
            $sql = "select * from TA_SalesJournalInvoice where JournalInvoiceID = '$InvoiceId' and Book='Party' 
             and CompanyCode = '$this->CompanyCode'  and IsDeleted=0";
            $invoiceDetailData = $this->getMultiData($sql);
            $sql = "select Total,Book from TA_SalesJournalInvoice where JournalInvoiceID = '$InvoiceId' and Book in ('IGSTPayble','SGSTPayble','CGSTPayble','RoundingOff')
             and CompanyCode = '$this->CompanyCode'  and IsDeleted=0";
            $invoiceTaxData = $this->getMultiData($sql);
            $invoiceTaxData = array_column($invoiceTaxData,'Total','Book');
            $InvoiceIRNJSONData = [];
            $InvoiceIRNJSONData['Version'] = '1.1';
            if(!empty($invoiceData)){
                $BuyerData = $this->getPartyInfo($invoiceData['PartyID']);
                $BuyerAddressData = $this->getAddressByPartyID($invoiceData['PartyID']);
                $BuyerDtls = [];
                $BuyerDtls['Gstin'] = (!empty($BuyerData['GSTN'])) ? $BuyerData['GSTN'] : '';
                $BuyerDtls['LglNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['TrdNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['Pos'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Supply
                $BuyerDtls['Stcd'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Buyer
                $BuyerDtls['Addr1'] = (!empty($BuyerAddressData['Address'])) ? substr($BuyerAddressData['Address'],0,99) : '';
                if((!empty($BuyerAddressData['Address2']))){
                    $BuyerDtls['Addr2'] = $BuyerAddressData['Address2'];
                }
                $BuyerDtls['Loc'] = (!empty($BuyerAddressData['Address3'])) ? $BuyerAddressData['Address3'] : '';
                $BuyerDtls['Pin'] = (!empty($BuyerAddressData['Pincode'])) ? intval($BuyerAddressData['Pincode']) : '';//Pin Code
                $InvoiceIRNJSONData['BuyerDtls'] = $BuyerDtls;
            }
            if(!empty($BranchData)){
                $SellerDtls = [];
                $SellerDtls['Gstin'] = (!empty($BranchData['GSTN'])) ? $BranchData['GSTN'] : '';
                $SellerDtls['LglNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['TrdNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['Stcd'] = (!empty($BranchData['BranchState'])) ? $BranchData['BranchState'] : '';//State Code Buyer
                $SellerDtls['Addr1'] = (!empty($BranchData['BranchAddress'])) ? substr($BranchData['BranchAddress'],0,99) : '';
                $SellerDtls['Loc'] = (!empty($BranchData['City'])) ? $BranchData['City'] : '';
                $SellerDtls['Pin'] = (!empty($BranchData['PinCode'])) ? intval($BranchData['PinCode']) : '';//Pin Code
                $SellerDtls['Ph'] =  (!empty($BranchData['Contact1'])) ? str_replace('-','',trim(explode(',',$BranchData['Contact1'])[0])) : '';
                $InvoiceIRNJSONData['SellerDtls'] = $SellerDtls;
            }
            if(!empty($invoiceData['DispFromBranchID']) && intval($invoiceData['DispFromBranchID']) > 0 && $invoiceData['DispFromBranchID'] != $$BranchData['BranchID']){
                $DisBranchData = $this->getBranchDetail($invoiceData['DispFromBranchID']);
                $DispDtls = [];
                $DispDtls['Nm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $DispDtls['Stcd'] = (!empty($DisBranchData['BranchState'])) ? $DisBranchData['BranchState'] : '';//State Code Buyer
                $DispDtls['Addr1'] = (!empty($DisBranchData['BranchAddress'])) ? substr($DisBranchData['BranchAddress'],0,99) : '';
                $DispDtls['Loc'] = (!empty($DisBranchData['City'])) ? $DisBranchData['City'] : '';
                $DispDtls['Pin'] = (!empty($DisBranchData['PinCode'])) ? intval($DisBranchData['PinCode']) : '';//Pin Code
                $InvoiceIRNJSONData['DispDtls'] = $DispDtls;
            }
            $ItemList = [];
            $GSTRates = $this->getGSTRates();
            $i= 1;
            foreach ($invoiceDetailData as $key => $itemData) {
                $invItem = [];
                $PartyData = $this->getPartyInfo($itemData['PartyID']);
                $Srno = $i++;
                $invItem['SlNo'] = "$Srno" ;
                $invItem['IsServc'] = 'Y';
                $invItem['HsnCd'] = $itemData['HSN'];
                $invItem['PrdDesc'] = $PartyData['Name'];
                $invItem['Qty'] = floatval(($itemData['RatePer'] == 0)? $itemData['Unit'] : $itemData['Qty']);
                $invItem['Unit'] = 'UNT';
                $invItem['UnitPrice'] = floatval($itemData['Rate']);
                
                $invItem['TotAmt'] = floatval($itemData['SubTotal'] );
                $invItem['Discount'] = floatval($itemData['Discount']);
                
                $invItem['PreTaxVal'] = floatval($itemData['Taxable']);

                $invItem['IgstAmt'] = floatval($itemData['IGST']);
                $invItem['CgstAmt'] = floatval($itemData['CGST']);
                $invItem['SgstAmt'] = floatval($itemData['SGST']);
                
                $invItem['AssAmt'] = floatval($itemData['Taxable']);
                $invItem['GstRt'] = floatval($GSTRates[$itemData['GSTID']]['GST']);
                $TotalValue = floatval($invItem['AssAmt']  + $invItem['SgstAmt'] + $invItem['IgstAmt'] + $invItem['CgstAmt']);
                $invItem['TotItemVal'] = round($TotalValue,2); // Not found in Total
                $ItemList[] =$invItem;
            }
            $InvoiceIRNJSONData['ItemList'] = $ItemList;
            $InvoiceIRNJSONData['ItemListSql'] = $sql;
            // $InvoiceIRNJSONData['invoiceDetailData'] = $invoiceDetailData;
            $TranDtls = [];
            $TranDtls['TaxSch'] = 'GST';
            $TranDtls['SupTyp'] = 'B2B';
            $InvoiceIRNJSONData['TranDtls'] = $TranDtls;
            $ValDtls = [];
            $ValDtls['CgstVal'] = floatval($invoiceTaxData['CGSTPayble']);
            $ValDtls['SgstVal'] = floatval($invoiceTaxData['SGSTPayble']);
            $ValDtls['IgstVal'] = floatval($invoiceTaxData['IGSTPayble']);
            $ValDtls['AssVal'] = floatval($invoiceData['Taxable']);
            $invoiceData['DiscountAmount'] = 0;
            $ValDtls['Discount'] = floatval($invoiceData['DiscountAmount']);
            $ValDtls['RndOffAmt'] = floatval($invoiceTaxData['RoundingOff']);
            $ValDtls['TotInvVal'] = floatval($invoiceData['Total']);
            $InvoiceIRNJSONData['ValDtls'] = $ValDtls;
            if(!empty($invoiceData)){
                $DocDtls =[];
                $INvoiceNo = '';
                if(!empty($invoiceData['Prefix'])){
                    $INvoiceNo .= $invoiceData['Prefix'];
                }
                $INvoiceNo .= $invoiceData['InvoiceNo'];
                $DocDtls['Typ']='INV'; //"Sales Journal"
                $DocDtls['No']=$INvoiceNo;
                $DocDtls['Dt']=date_format(date_create($invoiceData['InvoiceDate']),"d/m/Y");
                $InvoiceIRNJSONData['DocDtls'] = $DocDtls;
            }
            $AddressType = $invoiceData['sd_AddressType'];
            $DeliveryAddressDetail = [];
            $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['PartyID']);           
            if (!empty($invoiceData['HasteID'])) {
                $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['HasteID']);
                if(!empty($DeliveryAddressDetail)){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($invoiceData['HasteName'])) ? $invoiceData['HasteName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($invoiceData['HasteName'])) ? $DeliveryAddressDetail['HasteName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }else if(!empty($invoiceData['PrintPartyAddress'])  && $invoiceData['PrintPartyAddress'] != $DeliveryAddressDetail['AddressID']){
                $DeliveryAddressDetail = $this->getAddressByID($invoiceData['PrintPartyAddress']);
                if(!empty($DeliveryAddressDetail) && !empty($DeliveryAddressDetail['AliasName'])){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }
            // if(!empty($invoiceData['sd_distance'])){
                $EwbDtls = [];
                if(!empty($invoiceData['TransporterGSTN'])){
                    $EwbDtls['TransId'] = $invoiceData['TransporterGSTN'];
                }
                $EwbDtls['Distance'] = 0;
                if(!empty($invoiceData['sd_distance']) && intval($invoiceData['sd_distance']) > 0){
                    $EwbDtls['Distance'] = intval($invoiceData['sd_distance']);
                }   
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['TransporterName'])){
                    $EwbDtls['TransName'] = $invoiceData['TransporterName'];
                }else if(!empty($invoiceData['so_Transporter'])){
                    $EwbDtls['TransName'] = $invoiceData['so_Transporter'];
                }
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['sd_vehicleType'])){
                    $EwbDtls['VehType'] = $invoiceData['sd_vehicleType'];
                }
                if(!empty($invoiceData['sd_transMode'])){
                    $EwbDtls['TransMode'] = $invoiceData['sd_transMode'];
                }
                $InvoiceIRNJSONData['EwbDtls'] = $EwbDtls;
            // }
        }
        return $InvoiceIRNJSONData;
    }
    
    public function getSalesReturnDetailForIRN($InvoiceId){
        if(intval($InvoiceId) > 0){
            $BranchData = $this->getBranchDetail();
            $CompanyData = $this->getCompanyMaster();
            $sql = "SELECT * from TA_SalesReturn where SalesReturnID = '$InvoiceId';";
            $invoiceData = $this->getSingleData($sql);
            $sql = "SELECT bqm.SalesQualityName,mm.HSN,uom.Symbol, srd.* from TA_SalesReturnDetails srd
            left join TA_BeamQualityMst bqm on bqm.QualityID = srd.QualityID
            left join TA_UnitOfMeasure uom on uom.UnitOfMeasureID = srd.QtyPer
            left join TA_MaterialMst mm on mm.MaterialID = bqm.MaterialID 
            where srd.SalesReturnID = '$InvoiceId' and srd.IsDeleted = 0;";
            $invoiceDetailData = $this->getMultiData($sql);
            $InvoiceIRNJSONData = [];
            $InvoiceIRNJSONData['Version'] = '1.1';
            if(!empty($invoiceData)){
                $BuyerData = $this->getPartyInfo($invoiceData['BuyerID']);
                $BuyerAddressData = $this->getAddressByPartyID($invoiceData['BuyerID']);
                $BuyerDtls = [];
                $BuyerDtls['Gstin'] = (!empty($BuyerData['GSTN'])) ? $BuyerData['GSTN'] : '';
                $BuyerDtls['LglNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['TrdNm'] = (!empty($BuyerData['Name'])) ? $BuyerData['Name'] : '';
                $BuyerDtls['Pos'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Supply
                $BuyerDtls['Stcd'] = (!empty($BuyerAddressData['State'])) ? $BuyerAddressData['State'] : '';//State Code Buyer
                $BuyerDtls['Addr1'] = (!empty($BuyerAddressData['Address'])) ? substr($BuyerAddressData['Address'],0,99) : '';
                if((!empty($BuyerAddressData['Address2']))){
                    $BuyerDtls['Addr2'] = $BuyerAddressData['Address2'];
                }
                $BuyerDtls['Loc'] = (!empty($BuyerAddressData['Address3'])) ? $BuyerAddressData['Address3'] : '';
                $BuyerDtls['Pin'] = (!empty($BuyerAddressData['Pincode'])) ? intval($BuyerAddressData['Pincode']) : '';//Pin Code
                $InvoiceIRNJSONData['BuyerDtls'] = $BuyerDtls;
            }
            if(!empty($BranchData)){
                $SellerDtls = [];
                $SellerDtls['Gstin'] = (!empty($BranchData['GSTN'])) ? $BranchData['GSTN'] : '';
                $SellerDtls['LglNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['TrdNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['Stcd'] = (!empty($BranchData['BranchState'])) ? $BranchData['BranchState'] : '';//State Code Buyer
                $SellerDtls['Addr1'] = (!empty($BranchData['BranchAddress'])) ? substr($BranchData['BranchAddress'],0,99) : '';
                $SellerDtls['Loc'] = (!empty($BranchData['City'])) ? $BranchData['City'] : '';
                $SellerDtls['Pin'] = (!empty($BranchData['PinCode'])) ? intval($BranchData['PinCode']) : '';//Pin Code
                $SellerDtls['Ph'] =  (!empty($BranchData['Contact1'])) ? str_replace('-','',trim(explode(',',$BranchData['Contact1'])[0])) : '';
                $InvoiceIRNJSONData['SellerDtls'] = $SellerDtls;
            }
            if(!empty($invoiceData['DispFromBranchID']) && intval($invoiceData['DispFromBranchID']) > 0 && $invoiceData['DispFromBranchID'] != $$BranchData['BranchID']){
                $DisBranchData = $this->getBranchDetail($invoiceData['DispFromBranchID']);
                $DispDtls = [];
                $DispDtls['Nm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $DispDtls['Stcd'] = (!empty($DisBranchData['BranchState'])) ? $DisBranchData['BranchState'] : '';//State Code Buyer
                $DispDtls['Addr1'] = (!empty($DisBranchData['BranchAddress'])) ? substr($DisBranchData['BranchAddress'],0,99) : '';
                $DispDtls['Loc'] = (!empty($DisBranchData['City'])) ? $DisBranchData['City'] : '';
                $DispDtls['Pin'] = (!empty($DisBranchData['PinCode'])) ? intval($DisBranchData['PinCode']) : '';//Pin Code
                $InvoiceIRNJSONData['DispDtls'] = $DispDtls;
            }
            $ItemList = [];
            $GSTRates = $this->getGSTRates();
            $i= 1;
            foreach ($invoiceDetailData as $key => $itemData) {
                $invItem = [];
                $Srno = $i++;
                $invItem['SlNo'] = "$Srno" ;
                $invItem['IsServc'] = 'N';
                $invItem['HsnCd'] = $itemData['HSN'];
                $invItem['PrdDesc'] = $itemData['SalesQualityName'];
                $invItem['Qty'] = floatval($itemData['SQMtr']);
                $invItem['Unit'] = $itemData['Symbol'];
                $invItem['UnitPrice'] = floatval($itemData['RateSQMtr']);
                
                $invItem['TotAmt'] = floatval($itemData['Amount'] + $itemData['Freight']);
                $invItem['Discount'] = floatval($itemData['DiscountRs']);
                $invItem['Discount'] = floatval($itemData['DiscountRs']) + floatval($itemData['RDRs']);
                
                $invItem['PreTaxVal'] = floatval($itemData['TaxableValue']);

                $invItem['IgstAmt'] = floatval($itemData['IGST']);
                $invItem['CgstAmt'] = floatval($itemData['CGST']);
                $invItem['SgstAmt'] = floatval($itemData['SGST']);
                
                $invItem['AssAmt'] = floatval($itemData['TaxableValue']);
                $invItem['GstRt'] = floatval($GSTRates[$itemData['GSTID']]['GST']);
                $TotalValue = floatval($invItem['AssAmt']  + $invItem['SgstAmt'] + $invItem['IgstAmt'] + $invItem['CgstAmt']);
                $invItem['TotItemVal'] = round($TotalValue,2); // Not found in Total
                $ItemList[] =$invItem;
            }
            $InvoiceIRNJSONData['ItemList'] = $ItemList;
            $InvoiceIRNJSONData['ItemListSql'] = $sql;
            // $InvoiceIRNJSONData['invoiceDetailData'] = $invoiceDetailData;
            $TranDtls = [];
            $TranDtls['TaxSch'] = 'GST';
            $TranDtls['SupTyp'] = 'B2B';
            $InvoiceIRNJSONData['TranDtls'] = $TranDtls;
            $ValDtls = [];
            $ValDtls['CgstVal'] = floatval($invoiceData['CGST']);
            $ValDtls['SgstVal'] = floatval($invoiceData['SGST']);
            $ValDtls['IgstVal'] = floatval($invoiceData['IGST']);
            $ValDtls['AssVal'] = floatval($invoiceData['TaxableAmount']);
            $invoiceData['DiscountAmount'] = 0;
            $invoiceData['RateDiff'] = 0;
            $ValDtls['Discount'] = floatval($invoiceData['DiscountAmount']) + floatval($invoiceData['RateDiff']);
            $ValDtls['RndOffAmt'] = floatval($invoiceData['RoundOff']);
            $ValDtls['TotInvVal'] = floatval($invoiceData['Total']);
            $InvoiceIRNJSONData['ValDtls'] = $ValDtls;
            if(!empty($invoiceData)){
                $DocDtls =[];
                $INvoiceNo = '';
                if(!empty($invoiceData['Prefix'])){
                    $INvoiceNo .= $invoiceData['Prefix'];
                }
                $INvoiceNo .= $invoiceData['CreditNoteNo'];
                $DocDtls['Typ']='CRN'; //"CRN","DBN"
                $DocDtls['No']=$INvoiceNo;
                $DocDtls['Dt']=date_format(date_create($invoiceData['CreditNoteDate']),"d/m/Y");
                $InvoiceIRNJSONData['DocDtls'] = $DocDtls;
            }
            $AddressType = $invoiceData['sd_AddressType'];
            $DeliveryAddressDetail = [];
            $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['BuyerID']);           
            if (!empty($invoiceData['HasteID'])) {
                $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['HasteID']);
                $HastePartyInfo = $this->getPartyInfo($invoiceData['HasteID']);
                if(!empty($DeliveryAddressDetail)){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($HastePartyInfo['Name'])) ? $HastePartyInfo['Name'] : '';
                    $ShipDtls['TrdNm'] = (!empty($HastePartyInfo['Name'])) ? $HastePartyInfo['HasteName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }else if(!empty($invoiceData['PrintPartyAddress'])  && $invoiceData['PrintPartyAddress'] != $DeliveryAddressDetail['AddressID']){
                $DeliveryAddressDetail = $this->getAddressByID($invoiceData['PrintPartyAddress']);
                if(!empty($DeliveryAddressDetail) && !empty($DeliveryAddressDetail['AliasName'])){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }
            // if(!empty($invoiceData['sd_distance'])){
                $EwbDtls = [];
                if(!empty($invoiceData['TransporterGSTN'])){
                    $EwbDtls['TransId'] = $invoiceData['TransporterGSTN'];
                }
                $EwbDtls['Distance'] = 0;
                if(!empty($invoiceData['sd_distance']) && intval($invoiceData['sd_distance']) > 0){
                    $EwbDtls['Distance'] = intval($invoiceData['sd_distance']);
                }   
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['TransporterName'])){
                    $EwbDtls['TransName'] = $invoiceData['TransporterName'];
                }else if(!empty($invoiceData['so_Transporter'])){
                    $EwbDtls['TransName'] = $invoiceData['so_Transporter'];
                }
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['sd_vehicleType'])){
                    $EwbDtls['VehType'] = $invoiceData['sd_vehicleType'];
                }
                if(!empty($invoiceData['sd_transMode'])){
                    $EwbDtls['TransMode'] = $invoiceData['sd_transMode'];
                }
                $InvoiceIRNJSONData['EwbDtls'] = $EwbDtls;
            // }
        }
        return $InvoiceIRNJSONData;
    }
    public function GenerateIRN($input){
        $authRes = $this->auth($input);
        print_r($authRes);
        exit();
        if(empty($input['InvoiceId']) && intval($input['InvoiceId']) <= 0){
            return ['status'=>0,'Message'=>'InvoiceId Require'];
        }
        $InvoiceId = intval($input['InvoiceId']);
        $ServiceEInvoice = array_key_exists('ServiceEInvoice', $input) && $input['ServiceEInvoice'] == 'ServiceEInvoice' ? 'Y' : 'N';
        $invoiceData =  $this->getInvoiceDetailForIRN($InvoiceId, $ServiceEInvoice);

        // $userData = [
        //     'UserName' => 'AL001'
        // ];

        $url = "https://gstsandbox.charteredinfo.com/eicore/dec/v1.03/Invoice";
        if($this->Mode != 'Dev'){
            $url = 'https://gstsandbox.charteredinfo.com/eicore/dec/v1.03/Invoice';
        }
        $userData = $this->GetBranchGspCredentials();
       
        $header = [
            'Content-Type: application/json',
            'aspid: 1763812424',
            'User_name: '.$userData['UserName'],
            'Gstin: 34AACCC1596Q002',
            'AuthToken: '.$userData['AuthToken'], // Replace with actual AuthToken if needed
        ];

        $params = [
            'aspid' => '1763812424',
            'password' => 'P@ssw0rd',
            'Gstin' => '34AACCC1596Q002',
            'User_name' => $userData['UserName'],
            'eInvPwd' => $userData['Password'],
            'AuthToken' => $userData['AuthToken'],
            'QrCodeSize' => 250,
            'fortally' => 1,
            // 'ParseIrnResp' => 0,
        ];
        // Append the parameters to the URL
        $url .= '?' . http_build_query($params);

        // $data =  $this->data_encrypt($userData['Sek'],$invoiceData);
        // $data =  $invoiceData;
        $jsonData = [
            "Version" => "1.1",
            "TranDtls" => [
                "TaxSch" => "GST",
                "SupTyp" => "B2B",
                "RegRev" => "Y",
                "EcmGstin" => null,
                "IgstOnIntra" => "N"
            ],
            "DocDtls" => [
                "Typ" => "INV",
                "No" => "DOC/609555001",
                "Dt" => "15/06/2024"
            ],
            "SellerDtls" => [
                "Gstin" => "34AACCC1596Q002",
                "LglNm" => "NIC company pvt ltd",
                "TrdNm" => "NIC Industries",
                "Addr1" => "5th block, kuvempu layout",
                "Addr2" => "kuvempu layout",
                "Loc" => "GANDHINAGAR",
                "Pin" => 605001,
                "Stcd" => "34",
                "Ph" => "9000000000",
                "Em" => "abc@gmail.com"
            ],
            "BuyerDtls" => [
                "Gstin" => "29AWGPV7107B1Z1",
                "LglNm" => "XYZ company pvt ltd",
                "TrdNm" => "XYZ Industries",
                "Pos" => "12",
                "Addr1" => "7th block, kuvempu layout",
                "Addr2" => "kuvempu layout",
                "Loc" => "GANDHINAGAR",
                "Pin" => 562160,
                "Stcd" => "29",
                "Ph" => "91111111111",
                "Em" => "xyz@yahoo.com"
            ],
            "DispDtls" => [
                "Nm" => "ABC company pvt ltd",
                "Addr1" => "7th block, kuvempu layout",
                "Addr2" => "kuvempu layout",
                "Loc" => "Banagalore",
                "Pin" => 562160,
                "Stcd" => "29"
            ],
            "ItemList" => [
                [
                    "SlNo" => "1",
                    "PrdDesc" => "Rice",
                    "IsServc" => "N",
                    "HsnCd" => "1001",
                    "Barcde" => "12356",
                    "Qty" => 100.345,
                    "FreeQty" => 10,
                    "Unit" => "BAG",
                    "UnitPrice" => 99.545,
                    "TotAmt" => 9978.84,
                    "Discount" => 0,
                    "PreTaxVal" => 1,
                    "AssAmt" => 9978.84,
                    "GstRt" => 12.0,
                    "IgstAmt" => 1197.46,
                    "CgstAmt" => 0,
                    "SgstAmt" => 0,
                    "CesRt" => 5,
                    "CesAmt" => 498.94,
                    "CesNonAdvlAmt" => 10,
                    "StateCesRt" => 12,
                    "StateCesAmt" => 1197.46,
                    "StateCesNonAdvlAmt" => 5,
                    "OthChrg" => 10,
                    "TotItemVal" => 12897.7,
                    "OrdLineRef" => "3256",
                    "OrgCntry" => "AG",
                    "PrdSlNo" => "12345",
                    "BchDtls" => [
                        "Nm" => "123456",
                        "Expdt" => "01/08/2020",
                        "wrDt" => "01/09/2020"
                    ],
                    "AttribDtls" => [
                        [
                            "Nm" => "Rice",
                            "Val" => "10000"
                        ]
                    ]
                ]
            ],
            "ValDtls" => [
                "AssVal" => 9978.84,
                "CgstVal" => 0,
                "SgstVal" => 0,
                "IgstVal" => 1197.46,
                "CesVal" => 508.94,
                "StCesVal" => 1202.46,
                "Discount" => 10,
                "OthChrg" => 20,
                "RndOffAmt" => 0.3,
                "TotInvVal" => 12908,
                "TotInvValFc" => 12897.7
            ],
            "PayDtls" => [
                "Nm" => "ABCDE",
                "Accdet" => "5697389713210",
                "Mode" => "Cash",
                "Fininsbr" => "SBIN11000",
                "Payterm" => "100",
                "Payinstr" => "Gift",
                "Crtrn" => "test",
                "Dirdr" => "test",
                "Crday" => 100,
                "Paidamt" => 10000,
                "Paymtdue" => 5000
            ],
            "RefDtls" => [
                "InvRm" => "TEST",
                "DocPerdDtls" => [
                    "InvStDt" => "01/08/2020",
                    "InvEndDt" => "01/09/2020"
                ],
                "PrecDocDtls" => [
                    [
                        "InvNo" => "DOC/002",
                        "InvDt" => "01/08/2020",
                        "OthRefNo" => "123456"
                    ]
                ],
                "ContrDtls" => [
                    [
                        "RecAdvRefr" => "Doc/003",
                        "RecAdvDt" => "01/08/2020",
                        "Tendrefr" => "Abc001",
                        "Contrrefr" => "Co123",
                        "Extrefr" => "Yo456",
                        "Projrefr" => "Doc-456",
                        "Porefr" => "Doc-789",
                        "PoRefDt" => "01/08/2020"
                    ]
                ]
            ],
            "AddlDocDtls" => [
                [
                    "Url" => "https://einv-apisandbox.nic.in",
                    "Docs" => "Test Doc",
                    "Info" => "Document Test"
                ]
            ],
            "EwbDtls" => [
                "TransId" => null,
                "TransName" => null,
                "Distance" => 0,
                "TransDocNo" => null,
                "TransDocDt" => null,
                "VehNo" => "ka123456",
                "VehType" => "R",
                "TransMode" => "1"
            ]
        ];

        // Encode the data to JSON
        $data = json_encode($jsonData);
        // return $data;

        // $header =   array(
        //     'user_name:'. $userData['UserName'],
        //     'Gstin:'. $userData['GSTN'],
        //     'AuthToken:'.$userData['AuthToken'],
        //     'Ocp-Apim-Subscription-Key:' . $this->sub_key
        // );
        // $data = json_encode(['Data'=>$data]);
        

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER =>$header,
            CURLOPT_POSTFIELDS => $data,
        ));
        $response = curl_exec($ch);
        print_r($response);
        exit();

        $EInvoiceResponseData = [];
        $response = (Array) json_decode($response);
        if(!empty($response['Status']) && $response['Status'] == 1){
            $EInvoiceResponseData = (Array)  $this->data_decrypt($userData['Sek'],$response['Data']);
            $Edata = [];
            $Edata['InvoiceID'] = $InvoiceId;
            $Edata['AckNo'] = $EInvoiceResponseData['AckNo'];
            $Edata['AckDt'] = $EInvoiceResponseData['AckDt'];
            $Edata['Irn'] = $EInvoiceResponseData['Irn'];
            $Edata['SignedInvoice'] = mysqli_real_escape_string($this->cnn,$EInvoiceResponseData['SignedInvoice']);
            $Edata['SignedQRCode'] =  mysqli_real_escape_string($this->cnn,$EInvoiceResponseData['SignedQRCode']);
            $Edata['EwbNo'] = $EInvoiceResponseData['EwbNo'];
            $Edata['EwbDt'] = $EInvoiceResponseData['EwbDt'];
            $Edata['EwbValidTill'] = $EInvoiceResponseData['EwbValidTill'];
            $Edata['Status'] = $EInvoiceResponseData['Status'];
            if(!empty($Edata['EwbNo'])){
                $SalesOrderDelieveryData = [];
                $SalesOrderDelieveryData['ewayBillNo'] = $Edata['EwbNo'];
                $SalesOrderDelieveryData['ewayBillDate'] = $Edata['EwbDt'];
                $SalesOrderDelieveryData['validUpto'] = $Edata['EwbValidTill'];
                $sql = "select sd_SalesOrderDelieveryID as SalesOrderDelieveryID from SalesOrderInvoice where SalesOrderInvoiceID = '$InvoiceId';";
                $ChallaneData = $this->getSingleData($sql);
                $this->updateData('TA_SalesOrderDelievery',$SalesOrderDelieveryData, " SalesOrderDelieveryID in ($ChallaneData[SalesOrderDelieveryID])  ");
            }
            $this->insertData('TA_EInvoiceDetail',$Edata);
        }else{  
            $ErrorDetails = (Array) $response['ErrorDetails'];
            $errMsg = " <br>";
            foreach ($ErrorDetails as $key => $eror) {
                $eror1 = (Array)$eror;
                if($eror1["ErrorCode"] == "1005"){
                    $authRes = $this->auth($input);
                    return $this->GenerateIRN($input);
                }
                $errMsg .= " ( ".$eror1["ErrorCode"]." ) - ".$eror1["ErrorMessage"]." <br>";
            }
            return ["Message" =>$errMsg, "status" =>0,'response'=>$response,'invoiceData'=>$invoiceData];
        }
        return [
            'RequestData' => [
                'response'=>$response,
                'header'=>$header,
                'data'=>$data,
                'invoiceData'=>$invoiceData,
                'EInvoiceResponseData'=>$EInvoiceResponseData,
                'invoiceData'=>$invoiceData,
                'userData'=>$userData
            ]
        ];
    }
    public function getInvoiceDetailForIRN($InvoiceId, $ServiceEInvoice='N'){
        if(intval($InvoiceId) > 0){
            $BranchData = $this->getBranchDetail();
            $CompanyData = $this->getCompanyMaster();
            $sql = "select * from SalesOrderInvoice where SalesOrderInvoiceID = '$InvoiceId';";
            $invoiceData = $this->getSingleData($sql);
            $sql = "select * from SalesOrderInvoiceDetailsView  where SalesOrderInvoiceID = '$InvoiceId' and IsDeleted=0;";
            $invoiceDetailData = $this->getMultiData($sql);
            $InvoiceIRNJSONData = [];
            $InvoiceIRNJSONData['Version'] = '1.1';
            if(!empty($invoiceData)){
                $BuyerDtls = [];
                $BuyerDtls['Gstin'] = (!empty($invoiceData['GSTN'])) ? $invoiceData['GSTN'] : '';
                $BuyerDtls['LglNm'] = (!empty($invoiceData['BuyerName'])) ? $invoiceData['BuyerName'] : '';
                $BuyerDtls['TrdNm'] = (!empty($invoiceData['BuyerName'])) ? $invoiceData['BuyerName'] : '';
                $BuyerDtls['Pos'] = (!empty($invoiceData['State'])) ? $invoiceData['State'] : '';//State Code Supply
                $BuyerDtls['Stcd'] = (!empty($invoiceData['State'])) ? $invoiceData['State'] : '';//State Code Buyer
                $BuyerDtls['Addr1'] = (!empty($invoiceData['Address'])) ? substr($invoiceData['Address'],0,99) : '';
                if((!empty($invoiceData['Address2']))){
                    $BuyerDtls['Addr2'] = $invoiceData['Address2'];
                }
                $BuyerDtls['Loc'] = (!empty($invoiceData['Address3'])) ? $invoiceData['Address3'] : '';
                $BuyerDtls['Pin'] = (!empty($invoiceData['Pincode'])) ? intval($invoiceData['Pincode']) : '';//Pin Code
                $InvoiceIRNJSONData['BuyerDtls'] = $BuyerDtls;
            }
            if(!empty($BranchData)){
                $SellerDtls = [];
                $SellerDtls['Gstin'] = (!empty($BranchData['GSTN'])) ? $BranchData['GSTN'] : '';
                $SellerDtls['LglNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['TrdNm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $SellerDtls['Stcd'] = (!empty($BranchData['BranchState'])) ? $BranchData['BranchState'] : '';//State Code Buyer
                $SellerDtls['Addr1'] = (!empty($BranchData['BranchAddress'])) ? substr($BranchData['BranchAddress'],0,99) : '';
                $SellerDtls['Loc'] = (!empty($BranchData['City'])) ? $BranchData['City'] : '';
                $SellerDtls['Pin'] = (!empty($BranchData['PinCode'])) ? intval($BranchData['PinCode']) : '';//Pin Code
                $SellerDtls['Ph'] =  (!empty($BranchData['Contact1'])) ? str_replace('-','',trim(explode(',',$BranchData['Contact1'])[0])) : '';
                $InvoiceIRNJSONData['SellerDtls'] = $SellerDtls;
            }
            if(!empty($invoiceData['DispFromBranchID']) && intval($invoiceData['DispFromBranchID']) > 0 && $invoiceData['DispFromBranchID'] != $$BranchData['BranchID']){
                $DisBranchData = $this->getBranchDetail($invoiceData['DispFromBranchID']);
                $DispDtls = [];
                $DispDtls['Nm'] = (!empty($CompanyData['companyName'])) ? $CompanyData['companyName'] : '';
                $DispDtls['Stcd'] = (!empty($DisBranchData['BranchState'])) ? $DisBranchData['BranchState'] : '';//State Code Buyer
                $DispDtls['Addr1'] = (!empty($DisBranchData['BranchAddress'])) ? substr($DisBranchData['BranchAddress'],0,99) : '';
                $DispDtls['Loc'] = (!empty($DisBranchData['City'])) ? $DisBranchData['City'] : '';
                $DispDtls['Pin'] = (!empty($DisBranchData['PinCode'])) ? intval($DisBranchData['PinCode']) : '';//Pin Code
                $InvoiceIRNJSONData['DispDtls'] = $DispDtls;
            }
            $ItemList = [];
            $GSTRates = $this->getGSTRates();
            $i= 1;
            foreach ($invoiceDetailData as $key => $itemData) {
                $invItem = [];
                $Srno = $i++;
                $invItem['SlNo'] = "$Srno" ;
                $invItem['IsServc'] = $ServiceEInvoice;
                $invItem['HsnCd'] = $itemData['HSN'];
                $invItem['PrdDesc'] = $itemData['SalesQualityName'];
                $invItem['Qty'] = floatval($itemData['deld_SQMtr']);
                $invItem['Unit'] = $itemData['qUOMSymbol'];
                $invItem['UnitPrice'] = floatval($itemData['RateSQMtr']);
                
                $invItem['TotAmt'] = floatval($itemData['Amount'] + $itemData['Freight']);
                $invItem['Discount'] = floatval($itemData['DiscountRs']) + floatval($itemData['RDRs']);
                
                $invItem['PreTaxVal'] = floatval($itemData['TaxableValue']);

                $invItem['IgstAmt'] = floatval($itemData['IGST']);
                $invItem['CgstAmt'] = floatval($itemData['CGST']);
                $invItem['SgstAmt'] = floatval($itemData['SGST']);
                
                $invItem['AssAmt'] = floatval($itemData['TaxableValue']);
                $invItem['GstRt'] = floatval($GSTRates[$itemData['GSTID']]['GST']);
                $invItem['TotItemVal'] = floatval($itemData['TotalValue']);
                $ItemList[] =$invItem;
            }
            $InvoiceIRNJSONData['ItemList'] = $ItemList;
            // $InvoiceIRNJSONData['invoiceDetailData'] = $invoiceDetailData;
            $TranDtls = [];
            $TranDtls['TaxSch'] = 'GST';

            $SupTyp = 'B2B';
            if ($invoiceData['SEZ'] == 1) {
                $SupTyp = 'SEZWP';
            }else if ($invoiceData['SEZ'] == 2) {
                $SupTyp = 'SEZWOP';
            }

            $TranDtls['SupTyp'] = $SupTyp;
            $InvoiceIRNJSONData['TranDtls'] = $TranDtls;
            $ValDtls = [];
            $ValDtls['CgstVal'] = floatval($invoiceData['CGST']);
            $ValDtls['SgstVal'] = floatval($invoiceData['SGST']);
            $ValDtls['IgstVal'] = floatval($invoiceData['IGST']);
            $ValDtls['AssVal'] = floatval($invoiceData['TaxableAmount']);
            $invoiceData['DiscountAmount'] = 0;
            $invoiceData['RateDiff'] = 0;
            $ValDtls['Discount'] = floatval($invoiceData['DiscountAmount']) + floatval($invoiceData['RateDiff']);
            $ValDtls['OthChrg'] = floatval($invoiceData['TCSPayable']);
            $ValDtls['RndOffAmt'] = floatval($invoiceData['RoundOff']);
            $ValDtls['TotInvVal'] = floatval($invoiceData['Total']);
            $InvoiceIRNJSONData['ValDtls'] = $ValDtls;
            if(!empty($invoiceData)){
                $DocDtls =[];
                $INvoiceNo = '';
                if(!empty($invoiceData['Prefix'])){
                    $INvoiceNo .= $invoiceData['Prefix'];
                }
                $INvoiceNo .= $invoiceData['InvoiceNo'];
                $DocDtls['Typ']='INV';
                $DocDtls['No']=$INvoiceNo;
                $DocDtls['Dt']=date_format(date_create($invoiceData['InvoiceDate']),"d/m/Y");
                $InvoiceIRNJSONData['DocDtls'] = $DocDtls;
            }
            $AddressType = $invoiceData['sd_AddressType'];
            $DeliveryAddressDetail = [];
            $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['BuyerID']);           
            if (!empty($invoiceData['HasteID'])) {
                $DeliveryAddressDetail = $this->getAddressByPartyID($invoiceData['HasteID']);
                if(!empty($DeliveryAddressDetail)){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($invoiceData['HasteName'])) ? $invoiceData['HasteName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($invoiceData['HasteName'])) ? $DeliveryAddressDetail['HasteName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }else if(!empty($invoiceData['DeliverAddressID'])  && $invoiceData['DeliverAddressID'] != $DeliveryAddressDetail['AddressID']){
                $DeliveryAddressDetail = $this->getAddressByID($invoiceData['DeliverAddressID']);
                if(!empty($DeliveryAddressDetail) && !empty($DeliveryAddressDetail['AliasName'])){
                    $ShipDtls = [];
                    if((!empty(trim($DeliveryAddressDetail['GodownGSTN'])))  && strlen(trim($DeliveryAddressDetail['GodownGSTN'])) == 15 ){
                        $ShipDtls['Gstin'] = (!empty($DeliveryAddressDetail['GodownGSTN'])) ? $DeliveryAddressDetail['GodownGSTN'] : '';
                    }
                    if((!empty(trim($DeliveryAddressDetail['Address2'])))){
                        $ShipDtls['Addr2'] = (!empty($DeliveryAddressDetail['Address2'])) ? $DeliveryAddressDetail['Address2'] : '';
                    }
                    $ShipDtls['LglNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['TrdNm'] = (!empty($DeliveryAddressDetail['AliasName'])) ? $DeliveryAddressDetail['AliasName'] : '';
                    $ShipDtls['Stcd'] = (!empty($DeliveryAddressDetail['State'])) ? $DeliveryAddressDetail['State'] : '';//State Code Buyer
                    $ShipDtls['Addr1'] = (!empty($DeliveryAddressDetail['Address'])) ? substr($DeliveryAddressDetail['Address'],0,99) : '';
                    $ShipDtls['Loc'] = (!empty($DeliveryAddressDetail['Address3'])) ? $DeliveryAddressDetail['Address3'] : '';
                    $ShipDtls['Pin'] = (!empty($DeliveryAddressDetail['Pincode'])) ? intval($DeliveryAddressDetail['Pincode']) : '';//Pin Code
                    $InvoiceIRNJSONData['ShipDtls'] = $ShipDtls;
                }
            }
            // if(!empty($invoiceData['sd_distance'])){
                $EwbDtls = [];
                if(!empty($invoiceData['TransporterGSTN'])){
                    $EwbDtls['TransId'] = $invoiceData['TransporterGSTN'];
                }
                $EwbDtls['Distance'] = 0;
                if(!empty($invoiceData['sd_distance']) && intval($invoiceData['sd_distance']) > 0){
                    $EwbDtls['Distance'] = intval($invoiceData['sd_distance']);
                }   
                /* if(!empty($invoiceData['sd_LRNo'])){
                    $EwbDtls['TransDocNo'] = $invoiceData['sd_LRNo'];
                } */
               /*  if(!empty($invoiceData['sd_LRDate'])){
                    $EwbDtls['TransDocDt'] = $invoiceData['sd_LRDate'];
                } */
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['TransporterName'])){
                    $EwbDtls['TransName'] = $invoiceData['TransporterName'];
                }else if(!empty($invoiceData['so_Transporter'])){
                    $EwbDtls['TransName'] = $invoiceData['so_Transporter'];
                }
                if(!empty($invoiceData['sd_VehicleNo'])){
                    $EwbDtls['VehNo'] = $invoiceData['sd_VehicleNo'];
                }
                if(!empty($invoiceData['sd_vehicleType'])){
                    $EwbDtls['VehType'] = $invoiceData['sd_vehicleType'];
                }
                if(!empty($invoiceData['sd_transMode'])){
                    $EwbDtls['TransMode'] = $invoiceData['sd_transMode'];
                }
                $InvoiceIRNJSONData['EwbDtls'] = $EwbDtls;
            // }
        }
        return $InvoiceIRNJSONData;
    }
    public function data_encrypt($sek,$data)
    {
        $app_key="E9Vf1xYcMxKhukeuEJkpX6YGFQaaKfnjyFCMF9czwgM=";
        $de=openssl_decrypt(base64_decode($sek), 'AES-256-ECB', base64_decode($app_key), OPENSSL_RAW_DATA);
        $encryptionMethod = "AES-256-ECB"; 
        $encryptedMessage = openssl_encrypt(json_encode($data), $encryptionMethod, $de);
        return $encryptedMessage;
    }
    public function data_decrypt($sek,$data){
        $app_key="E9Vf1xYcMxKhukeuEJkpX6YGFQaaKfnjyFCMF9czwgM=";
        $de=openssl_decrypt(base64_decode($sek), 'AES-256-ECB', base64_decode($app_key), OPENSSL_RAW_DATA);
        $encryptionMethod = "AES-256-ECB"; 
        $encryptedMessage = openssl_decrypt($data, $encryptionMethod, $de);      
        return json_decode($encryptedMessage);
    }
    public function CancelIRN($input){
        $url = "https://developers.eraahi.com/eInvoiceGateway/eiewb/v1.03/ewaybill/irn/";
        if($this->Mode != 'Dev'){
            $url = 'https://temp.alankit.com/eInvoiceGateway/eiewb/v1.03/ewaybill';
            $userData = $this->GetBranchGspCredentials();
        }
        $Body =[
            'Irn' => $input['Irn'],
            'CnlRsn'=> "Wrong entry",
            'CnlRem'=> "Wrong entry"
        ];
        $data =  $this->data_encrypt($userData['Sek'],$Body);
        $header =   array(
            'AuthToken:'.$userData['AuthToken'],
            'user_name:'. $userData['UserName'],
            'Gstin:'. $userData['GSTN'],    
            'Ocp-Apim-Subscription-Key:' . $this->sub_key
        );
        $data = json_encode(['Data'=>$data]);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>$header,
            CURLOPT_POSTFIELDS => $data,
        ));
        $response = curl_exec($ch);
        $response = (Array) json_decode($response);
        if(!empty($response['Status']) && $response['Status'] == 1){
            $EInvoiceResponseData = (Array)  $this->data_decrypt($userData['Sek'],$response['Data']);
            $data =['Status'=>'CNL'];
            $this->updateData('TA_EInvoiceDetail',$data, "  Irn = '$input[Irn]' ");
            return ["Message" =>"Canceled", "status" =>1,'EInvoiceResponseData'=>$EInvoiceResponseData];
        }else{  
            $ErrorDetails = (Array) $response['ErrorDetails'];
            $errMsg = " <br>";
            foreach ($ErrorDetails as $key => $eror) {
                $eror1 = (Array)$eror;
                if($eror1["ErrorCode"] == "1005"){
                    $this->auth($input);
                    return $this->GenerateIRN($input);
                }
                $errMsg .= " ( ".$eror1["ErrorCode"]." ) - ".$eror1["ErrorMessage"]." <br>";
            }
            return ["Message" =>$errMsg, "status" =>0,'response'=>$response];
        }
    }
    public function GenerateEWaybillByIrn($input) {
        $url = "https://developers.eraahi.com/eInvoiceGateway/eiewb/v1.03/ewaybill/irn/";
        if($this->Mode != 'Dev'){
            $url = 'https://temp.alankit.com/eInvoiceGateway/eiewb/v1.03/ewaybill';
            $userData = $this->GetBranchGspCredentials();
        }  
    }
}
if(isset($_POST['op'])){
    try {
        $obj = new EInvoiceAPIs($cnn);
        $m =  $_POST['op'];
        $res = $obj->$m($_POST);
        $res['errMsg'] = $obj->msg;
        if (!isset($res['status'])) {
            $res['status'] = 1;
        }
    } catch (\Throwable $th) {
        $res['status'] = 0;
        $res['message'] = $th->getMessage();
    }
} else{
    $printObject = new EInvoiceAPIs($cnn);
}
echo json_encode($res);