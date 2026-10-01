/* Exercise real PHP multipart uploads and private assembly without application DB. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const net = require('node:net');
const { spawn, spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(),'ersan-chunk-http-test-'));
const token = crypto.randomBytes(16).toString('hex');
const dirs = new Set();
let php;
const key = crypto.randomUUID();
const digest = b => crypto.createHash('sha256').update(b).digest('hex');
(async()=>{
  const probe=net.createServer();await new Promise(r=>probe.listen(0,'127.0.0.1',r));const port=probe.address().port;await new Promise(r=>probe.close(r));
  const source = `<?php
if (PHP_SAPI !== 'cli-server' || ($_SERVER['HTTP_X_TEST_TOKEN'] ?? '') !== '${token}') { http_response_code(404); exit; }
require '${root}/vendor/autoload.php';
header('Content-Type: application/json');
$s = new \\App\\Service\\PwaChunkUploadService();
try {
$result = $s->run(2147481000, 2147481000, (string) $_POST['key'], function($dir, $meta) use ($s) {
 $action = $_POST['action'];
 if ($action === 'start') { $meta = ['size'=>(int)$_POST['size'],'count'=>(int)ceil((int)$_POST['size']/262144),'hash'=>$_POST['hash'],'updated'=>time()]; $s->save($dir,$meta); }
 if ($action === 'chunk') { $s->put($dir,$meta,(int)$_POST['index'],$_FILES['chunk'],$_POST['hash']); $s->save($dir,$meta); }
 if ($action === 'finish') { $p=$s->assemble($dir,$meta); return ['hash'=>hash_file('sha256',$p),'size'=>filesize($p),'trusted'=>\\App\\Service\\PwaChunkUploadService::isStagedFile($p),'dir'=>$dir]; }
 if ($action === 'store') { $p=$s->assemble($dir,$meta); $v=(new \\App\\Service\\VideoUploadService())->storeAssembled(['tmp_name'=>$p,'size'=>filesize($p),'error'=>0], '${temp}/out', 'test', ['video/mp4'], 36700160, 90, (int)$_POST['duration']); return ['stored'=>is_file($v['path']),'dir'=>$dir]; }
 if ($action === 'expire') { $meta['updated']=time()-604801; file_put_contents($dir.'/meta.json',json_encode($meta)); return ['dir'=>$dir]; }
 return ['parts'=>$meta ? $s->parts($dir,$meta):[],'expired'=>$meta===null,'dir'=>$dir];
}); echo json_encode(['success'=>true,'data'=>$result]);
} catch (Throwable $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
`;
  fs.writeFileSync(path.join(temp,'index.php'),source);
  php=spawn(process.env.PHP_BINARY || '/opt/lampp/bin/php',['-S',`127.0.0.1:${port}`,'-t',temp],{stdio:'ignore'});
  const base=`http://127.0.0.1:${port}/index.php`;
  for(let i=0;i<50;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,50));}}
  async function request(action,fields={},blob=null,videoKey=key){
    const fd=new FormData();fd.append('action',action);fd.append('key',videoKey);for(const [k,v]of Object.entries(fields))fd.append(k,String(v));if(blob)fd.append('chunk',new Blob([blob]),'part.bin');
    const res=await fetch(base,{method:'POST',headers:{'X-Test-Token':token},body:fd});const json=await res.json();if(json.data?.dir)dirs.add(json.data.dir);return json;
  }
  const bytes=crypto.randomBytes(600000);await request('start',{size:bytes.length,hash:digest(bytes)});
  assert.equal((await request('chunk',{index:0,hash:'0'.repeat(64)},bytes.subarray(0,262144))).success,false);
  assert.equal((await request('chunk',{index:9,hash:digest(bytes.subarray(0,262144))},bytes.subarray(0,262144))).success,false);
  assert.equal((await request('chunk',{index:0,hash:digest(bytes.subarray(0,100))},bytes.subarray(0,100))).success,false);
  console.log('PASS invalid hash, index and size are rejected');
  assert.equal((await request('finish')).success,false);
  for(let i=0;i<3;i++){const chunk=bytes.subarray(i*262144,Math.min(bytes.length,(i+1)*262144));assert.equal((await request('chunk',{index:i,hash:digest(chunk)},chunk)).success,true);}
  const duplicate=await request('chunk',{index:0,hash:digest(bytes.subarray(0,262144))},bytes.subarray(0,262144));assert.deepEqual(duplicate.data.parts,[0,1,2]);
  const changed=Buffer.from(bytes.subarray(0,262144));changed[0]^=255;assert.equal((await request('chunk',{index:0,hash:digest(changed)},changed)).success,false);
  console.log('PASS duplicate part is accepted, altered duplicate is rejected');
  const done=await request('finish');assert.equal(done.data.hash,digest(bytes));assert.equal(done.data.size,bytes.length);assert.equal(done.data.trusted,true);
  console.log('PASS assembly verifies full byte count and SHA-256');
  assert.equal((await request('store',{duration:20})).success,false);console.log('PASS assembled invalid MIME is rejected');
  await request('expire');const expired=await request('status');assert.equal(expired.data.expired,true);assert.deepEqual(expired.data.parts,[]);assert.equal(fs.existsSync(path.join(expired.data.dir,'0.part')),false);
  console.log('PASS seven-day expired staging is cleared');
  const sample=path.join(temp,'sample.mp4');const ffmpeg=spawnSync('ffmpeg',['-hide_banner','-loglevel','error','-f','lavfi','-i','color=c=black:s=32x32:d=1','-c:v','libx264','-pix_fmt','yuv420p',sample]);assert.equal(ffmpeg.status,0);
  const video=fs.readFileSync(sample);const second=crypto.randomUUID();await request('start',{size:video.length,hash:digest(video)},null,second);await request('chunk',{index:0,hash:digest(video)},video,second);
  assert.equal((await request('store',{duration:91},null,second)).success,false);assert.equal((await request('store',{duration:1},null,second)).data.stored,true);
  console.log('PASS duration limit and valid assembled MP4 storage');
  console.log('6 PHP HTTP scenarios passed');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{if(php)php.kill();for(const dir of dirs)fs.rmSync(dir,{recursive:true,force:true});fs.rmSync(temp,{recursive:true,force:true});});
