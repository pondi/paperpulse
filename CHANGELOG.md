# Changelog

All notable changes to this project will be documented in this file.

## [1.4.0](https://github.com/pondi/paperpulse/compare/v1.3.5...v1.4.0) (2026-10-09)


### Features

* **activity:** add compact searchable file rows ([5bee9fe](https://github.com/pondi/paperpulse/commit/5bee9fe4ba16222fccfa626c9e90b26319f783d8))
* add folder recommendation review ([1f18c36](https://github.com/pondi/paperpulse/commit/1f18c3608f89cd4ad57f90bb70d2862d91a5c37b))
* apply and undo folder recommendations ([83e1bd9](https://github.com/pondi/paperpulse/commit/83e1bd9775597464a2b0b6a919cc352b1a485e6a))
* automate file processing and contextual collection grouping ([425aa3b](https://github.com/pondi/paperpulse/commit/425aa3b88916c5dcc4aed0363e16c44b26ca216b))
* backfill archives with saved folder rules ([217436d](https://github.com/pondi/paperpulse/commit/217436dafd5e59411ee2563e9148d4e6d0014dbd))
* bound and queue large archive exports ([7531f0f](https://github.com/pondi/paperpulse/commit/7531f0f617c73ec4c84e5d2ba49d1bcd10fc0fd5))
* connect expiry widgets and retire unused feature paths ([191bfaf](https://github.com/pondi/paperpulse/commit/191bfafd1ca9e1180494179036aa4c35f323d316))
* convert summaries with historical exchange rates ([cdbbdd3](https://github.com/pondi/paperpulse/commit/cdbbdd32f6fef08eb3b6bc969783a558483f2abe))
* **currency:** sync historical Norges Bank rates automatically ([44df78b](https://github.com/pondi/paperpulse/commit/44df78bb3a273414c35cf46800ba8323a8171883))
* enable opt-in expiry reminders ([46f3031](https://github.com/pondi/paperpulse/commit/46f3031d78b4f4bf8c7a63544c6ce79f78dae997))
* expose owned file extraction reports ([ca59dd3](https://github.com/pondi/paperpulse/commit/ca59dd3dfd34fb888ea8036b6f9acd6b44028008))
* **files:** show durable processing progress ([ef1daba](https://github.com/pondi/paperpulse/commit/ef1dabaa6c0bc63f946cb81533578fe8e1e46b87))
* **files:** support scoped archive reprocessing with safe retries ([2ed0d44](https://github.com/pondi/paperpulse/commit/2ed0d44de5ea7e6caee3881c0edb5803c33fecf3))
* generate bounded folder recommendations ([1b5a2d4](https://github.com/pondi/paperpulse/commit/1b5a2d46858012c6f000f94edaaab3c51a149bda))
* **home:** surface archive activity and attention ([fb18660](https://github.com/pondi/paperpulse/commit/fb18660b8397289702c2dd0283bc8dfe4bb635ef))
* **library:** simplify navigation and add saved views ([f09eb77](https://github.com/pondi/paperpulse/commit/f09eb77efd0cf1175bf6de3a1b58d10e422fe10e))
* persist organization review runs ([af38887](https://github.com/pondi/paperpulse/commit/af3888790ee2e6ae20ab9dba7c403f842ed763a8))
* queue generation-aware organization after extraction ([55ddb4d](https://github.com/pondi/paperpulse/commit/55ddb4d7954eb1943f35eb0140b3e593c46bd98c))
* respect saved organization choices ([3cc9b71](https://github.com/pondi/paperpulse/commit/3cc9b7193c74004aafe95d777b41c93588a70b30))
* **review:** confirm reconciled receipt totals ([054c32e](https://github.com/pondi/paperpulse/commit/054c32e38636d2a147fa728a347086ddbece1d1e))
* **runtime:** add PHP 8.5 PostgreSQL containers ([21a8d01](https://github.com/pondi/paperpulse/commit/21a8d0115d7bcfdce09c2faeaa0d18d92b7eee9f))
* support native Forge database workers ([6141509](https://github.com/pondi/paperpulse/commit/6141509ebde5aef74ef4c33c3ba671108ee80fb4))
* **ui:** refine navigation, home and document review ([47e307c](https://github.com/pondi/paperpulse/commit/47e307cbb8170092e0b710386b65d79ea84d6e9b))
* **workspace:** unify document review across home library and collections ([e04c245](https://github.com/pondi/paperpulse/commit/e04c245dfdf6b96f73b91a9360995cc8b7447521))


### Bug Fixes

* **a11y:** name dialogs with their visible headings ([1a7c50c](https://github.com/pondi/paperpulse/commit/1a7c50cbc91763a4016c03afd3de9053644e5658))
* **a11y:** name folder and category actions ([a376491](https://github.com/pondi/paperpulse/commit/a3764918b2aceb617f8d909ebf71cf10b1f6aca1))
* **a11y:** name record selection controls ([941bfc3](https://github.com/pondi/paperpulse/commit/941bfc377833ff2ad9f7d43769f8bceb67fd09e3))
* **activity:** include review files in summary ([3a645c9](https://github.com/pondi/paperpulse/commit/3a645c9fe2c64b310e9269876a26f2e912a93a3c))
* **activity:** wrap mobile recovery actions ([a34b2df](https://github.com/pondi/paperpulse/commit/a34b2df92a0fe9bac14a1482c92f900c2b1237cd))
* **ai:** align Gemini model defaults and extraction metadata ([e4302b3](https://github.com/pondi/paperpulse/commit/e4302b3f93403b8c65aa0a80c472a9eb747d4bbc))
* align shared document frontend contracts ([e70a6cf](https://github.com/pondi/paperpulse/commit/e70a6cfdebcd9d600af79f65db01d015fc240603))
* **analytics:** count warning arrays on PostgreSQL ([5091b70](https://github.com/pondi/paperpulse/commit/5091b7026baf092ffc4ab560d9cce8c148931c58))
* **analytics:** load source file names ([ac9e765](https://github.com/pondi/paperpulse/commit/ac9e765ee235aa3a89d355fb98cb4d52c59854f1))
* **analytics:** load source identities across owners ([58ce3a2](https://github.com/pondi/paperpulse/commit/58ce3a239667842b901fa53776e6d07dd3b94376))
* apply consistent file retention ([2ac076b](https://github.com/pondi/paperpulse/commit/2ac076bced8af008131e65794ac340984a1fb548))
* bound populated tenant and tag backfills ([48b7e1b](https://github.com/pondi/paperpulse/commit/48b7e1b3669c342871a963fe7b923f8e0b24e956))
* **bulk:** cancel files without storage keys ([32475eb](https://github.com/pondi/paperpulse/commit/32475eb961fe7ec89d91935b164ac33e46e9bd7a))
* **bulk:** compare cleanup job IDs as text ([fc60174](https://github.com/pondi/paperpulse/commit/fc6017450bdd8763557fa9c4190bab99842ee966))
* **bulk:** confirm uploads atomically ([cab1d78](https://github.com/pondi/paperpulse/commit/cab1d78669020e792bad2d28a7f6ffd53c5a9f6c))
* **bulk:** reconcile terminal processing results ([1d77d0e](https://github.com/pondi/paperpulse/commit/1d77d0e39ac4108af540dbc40dc938f6dcddd8ca))
* **bulk:** retry source cleanup after upload grants expire ([3f03fb7](https://github.com/pondi/paperpulse/commit/3f03fb776bbc44b9b6feb9a1f813b5e6afa4e5ba))
* **categories:** add scoped receipt browsing ([51cd61d](https://github.com/pondi/paperpulse/commit/51cd61d765972a33bf27d42ae1feaed0060a675a))
* **categories:** show document usage counts ([e4c349d](https://github.com/pondi/paperpulse/commit/e4c349df929215f12cccc6d94e0570abe8bec55e))
* claim file hashes atomically per user ([e0f33eb](https://github.com/pondi/paperpulse/commit/e0f33ebf1bf647dc664c6bb66554bca5ce1d5582))
* claim weekly summaries by local reporting period ([e357ae2](https://github.com/pondi/paperpulse/commit/e357ae20711b6331f2edfe3f553c8e8ff8450c64))
* **collections:** clear removed primary placement ([19bf98b](https://github.com/pondi/paperpulse/commit/19bf98b170c6f98e0a9060d241149a58e55f572b))
* **collections:** distinguish direct files and subtree totals ([8657d33](https://github.com/pondi/paperpulse/commit/8657d336f08e1f5c36f7e288326a13cdc3a258b3))
* **collections:** distinguish failed recommendations ([4f3a3d5](https://github.com/pondi/paperpulse/commit/4f3a3d531e07053a4f0fabfd7dcfee53dbfea885))
* **collections:** enable keyboard folder navigation ([ea7b689](https://github.com/pondi/paperpulse/commit/ea7b689db4df572f9736216bfd7acd8ad0f90cce))
* **collections:** enlarge mobile action targets ([a261ac0](https://github.com/pondi/paperpulse/commit/a261ac0c637543461bf77a2a6559acdfdcd3b429))
* **collections:** explain empty direct file lists ([13b21d9](https://github.com/pondi/paperpulse/commit/13b21d912565f25017bd7370b357e3afe1533302))
* **collections:** prioritize folders and files ([8cd19a6](https://github.com/pondi/paperpulse/commit/8cd19a6734b8453d757d18e9380414281bdc3596))
* **collections:** remove obsolete file loader ([95dc425](https://github.com/pondi/paperpulse/commit/95dc4255c3c6c36e8f77dd89af960b7de221945f))
* **collections:** retain subfolder parent context ([5d53080](https://github.com/pondi/paperpulse/commit/5d53080cd85f5be175fac10db00120e01b717e56))
* **collections:** return nested folders to parent ([e7830da](https://github.com/pondi/paperpulse/commit/e7830dab0d0c8f798a3fc55ec2d9a2157ec4d9d9))
* **collections:** show ancestor paths in selectors ([4ea39bb](https://github.com/pondi/paperpulse/commit/4ea39bb20ca92b79ca59581a54107aaf41e88632))
* **collections:** show compact folder rows and child paths ([f25d90e](https://github.com/pondi/paperpulse/commit/f25d90e91a740b8c9175c80e0529fa3ba5a88ac4))
* **collections:** wrap mobile folder actions ([e7d0e42](https://github.com/pondi/paperpulse/commit/e7d0e42c6e1521ae3f03a3aee6678e119e10ea3f))
* **conversion:** resume extraction on database queues ([c27a504](https://github.com/pondi/paperpulse/commit/c27a50446c56a08abcde15ab874e54025584836a))
* **conversion:** use local LibreOffice only ([24c152e](https://github.com/pondi/paperpulse/commit/24c152e0bcb3fe07c647997632eadb31d3a87cb8))
* **conversion:** use UUID chain IDs ([09497d8](https://github.com/pondi/paperpulse/commit/09497d827759bf1547d4e35b6375975e38848dfe))
* **csp:** allow HTTP development form submissions ([a42dadd](https://github.com/pondi/paperpulse/commit/a42dadd73dfabacb5ae69282a2ce41aaa58becd4))
* **csp:** nonce Inertia runtime styles ([e1f53a2](https://github.com/pondi/paperpulse/commit/e1f53a213baecb2ca841f192fe9119afe3073cf6))
* **csp:** support scanner and configured websockets ([9bcf2b6](https://github.com/pondi/paperpulse/commit/9bcf2b6657613d71bd47c845b8a87ad6aaf65695))
* **dashboard:** keep receipt amounts visible on phones ([1be56c6](https://github.com/pondi/paperpulse/commit/1be56c6c82dff5cdb97bf214d1dae8fb4db59afe))
* **database:** reject unsupported drivers before connecting ([e4ee328](https://github.com/pondi/paperpulse/commit/e4ee32844fb62e783b48dc409587f008d420bd5e))
* deduplicate active bulk manifest entries ([b866004](https://github.com/pondi/paperpulse/commit/b866004e59b037c26c170cea2e1998098127a925))
* **details:** expand tables and simplify scrolling ([5008006](https://github.com/pondi/paperpulse/commit/50080066b3793e2a9d9da4259cd40c850f86dfc0))
* **details:** stack mobile receipt and invoice layouts ([100d20c](https://github.com/pondi/paperpulse/commit/100d20c53e84f0f4045e922319e2e89b6391555f))
* discover first scheduled scanner uploads ([87bcedf](https://github.com/pondi/paperpulse/commit/87bcedfa6093fc946d4c51c0f06c032bf55891e9))
* dispatch batches after commit and honor cancellation ([849f3cd](https://github.com/pondi/paperpulse/commit/849f3cdeb95b9c6ebfb4a4344760020f92a0b9d3))
* distinguish analyzed document metadata ([d8a074b](https://github.com/pondi/paperpulse/commit/d8a074bf4d3ff503ae0dee587c8ef9f402a2b402))
* **docker:** exclude host settings and credentials ([3a6d4d5](https://github.com/pondi/paperpulse/commit/3a6d4d51150c47c1cdd63f55315759ab4b14c223))
* **documents:** bound analyzed titles ([54e30e2](https://github.com/pondi/paperpulse/commit/54e30e204d6123368f8e77dea9629a85766272a4))
* **documents:** bound extracted titles ([bbe0b2f](https://github.com/pondi/paperpulse/commit/bbe0b2fb1df21a0874c3293eb924e9baec6f1a47))
* **documents:** download canonical source files ([eaa5b3c](https://github.com/pondi/paperpulse/commit/eaa5b3c841666785b3022c9589d9bdfdb635ff67))
* **documents:** render disabled pagination safely ([738054e](https://github.com/pondi/paperpulse/commit/738054e0e5e6988a3ccb44b1a401d6bb693f3b82))
* **documents:** restore detail date formatter ([f4e4c0b](https://github.com/pondi/paperpulse/commit/f4e4c0bd0535d72149bf713c1af1e7d1f27063aa))
* **documents:** use file IDs for mixed bulk actions ([7a559b6](https://github.com/pondi/paperpulse/commit/7a559b688d01095e5a69cd76454078da894e22f0))
* **duplicates:** display candidate comparisons ([88bfd55](https://github.com/pondi/paperpulse/commit/88bfd55a3f65729a94cc4b3232ec84b52d47bc4c))
* eager load file search relations ([ca86a8f](https://github.com/pondi/paperpulse/commit/ca86a8f468d43b12716556913f7be2a57a5db370))
* **entities:** paginate filtered web lists ([84c1fa2](https://github.com/pondi/paperpulse/commit/84c1fa2efac16aac47605c9fa866531a08e14413))
* **entities:** validate and preserve manual edits ([615b960](https://github.com/pondi/paperpulse/commit/615b960efd7c2f1426da7a50bf753f5e7d062b9d))
* **exchange-rates:** skip krone index series ([9ec63b5](https://github.com/pondi/paperpulse/commit/9ec63b5504d9568977d5725e0bc1e48b3c98772d))
* exclude deleted merchant and vendor activity ([66f7f67](https://github.com/pondi/paperpulse/commit/66f7f67bfecf2247c13bedc76b252b5945137366))
* **exports:** guide creation and show expiry ([cad0a0f](https://github.com/pondi/paperpulse/commit/cad0a0f31bc76f2c693788193c80b3e0769606fc))
* extract batch items from owned files ([12a2633](https://github.com/pondi/paperpulse/commit/12a2633a5abf99fc19fa94f3baac308aaffc6039))
* **factories:** infer owners outside tenant scope ([c9fe7aa](https://github.com/pondi/paperpulse/commit/c9fe7aaa1edc71752dcdf47f6f135cb386a65e3b))
* **factories:** reconcile invoice amounts ([d589571](https://github.com/pondi/paperpulse/commit/d589571bd3e3cff8d1f47d5fb9b4bb0eade0850e))
* **factory:** infer invoice owner without auth scope ([066f1f5](https://github.com/pondi/paperpulse/commit/066f1f56904715d38e12f2bf76fdedda51af88ab))
* **files:** capture current cleanup paths ([e9a2ef6](https://github.com/pondi/paperpulse/commit/e9a2ef6decf5a0927f3f049b82aa5f8eee807590))
* **files:** explain processing limits and recovery ([a3753f9](https://github.com/pondi/paperpulse/commit/a3753f9dcaeb5cefb9ac40e3303ab4a164fcd6c6))
* **files:** format processing dates and verify browser upload flows ([321c863](https://github.com/pondi/paperpulse/commit/321c863d1ff7a0841ba421decbaf855e34567edb))
* **files:** isolate and clean job working directories ([987425c](https://github.com/pondi/paperpulse/commit/987425c7de1b2f5f3052a2c3b43b393e4417d6c5))
* **files:** preserve uploaded filenames ([9ceff6b](https://github.com/pondi/paperpulse/commit/9ceff6bed0dddce4e8b7f2ab47b146ccfff7508b))
* **files:** resolve links from recorded variants ([69a9891](https://github.com/pondi/paperpulse/commit/69a9891dc2db2f6a2a566a16e921987409a67b2a))
* **files:** retire obsolete retention path ([ee26095](https://github.com/pondi/paperpulse/commit/ee26095e42593355b7e1e2ddf85d1bb59e0fd5e5))
* **files:** show actionable failure diagnostics ([78d6392](https://github.com/pondi/paperpulse/commit/78d6392ecb525b213a2d94e76be9fdab9e47638f))
* **files:** use current paths for cleanup references ([50cd3aa](https://github.com/pondi/paperpulse/commit/50cd3aaf8a4261c80a284ae64de1542fb4f0df75))
* format dates and currencies from saved preferences ([863ab50](https://github.com/pondi/paperpulse/commit/863ab50449185aacadb390a764319f3fec7a8c80))
* **gemini:** retry transient token counting failures ([c30922c](https://github.com/pondi/paperpulse/commit/c30922ce0385abca97bd31d6130a26d885e5fe6b))
* **i18n:** resolve interface labels in selected locale ([78e0e6d](https://github.com/pondi/paperpulse/commit/78e0e6db03c83d062a06b6d60fb75517a8c7db0c))
* **imports:** explain scanner setup and sync ([905870f](https://github.com/pondi/paperpulse/commit/905870f0fd8649106d40c21560430012274f725b))
* **imports:** open folder tag dialog ([fc94d30](https://github.com/pondi/paperpulse/commit/fc94d303bebc48d41a00722864c29b774321a27c))
* **imports:** recover scanner folder navigation ([880285c](https://github.com/pondi/paperpulse/commit/880285c2f501bbcabeaa98082d8959704402f714))
* **invoice:** preserve extraction defaults ([01bbbaf](https://github.com/pondi/paperpulse/commit/01bbbafa5865b5572a0b8108a1cae16b0944dc81))
* **invoices:** distinguish missing line item values ([24afdeb](https://github.com/pondi/paperpulse/commit/24afdebf166668470922d8983a56df40575fe868))
* **invoices:** expose source file collections ([f953a1d](https://github.com/pondi/paperpulse/commit/f953a1dd289bf167e074f5224a81693eac6e2149))
* isolate migration lock sessions ([fe765d2](https://github.com/pondi/paperpulse/commit/fe765d292ee28fbca2fe255d75dd47e83e0b33b1))
* isolate scanner offline caches ([d68028a](https://github.com/pondi/paperpulse/commit/d68028a4ef9414499afc0ea55243430f0379c42f))
* **jobs:** restart durable file pipelines ([db40a96](https://github.com/pondi/paperpulse/commit/db40a961763200a954bac69495f370764256e521))
* keep category assignments and totals consistent ([797eb08](https://github.com/pondi/paperpulse/commit/797eb08b64222cdfed89d623dd1a22cd8fa33120))
* **library:** consolidate type and view controls ([fd0251a](https://github.com/pondi/paperpulse/commit/fd0251a20d773f1051464544884350d079d8ec6e))
* **library:** display extracted file identities ([be3aeba](https://github.com/pondi/paperpulse/commit/be3aebac2a334fc5d3f03084f0e129e43822bcdb))
* **logos:** preserve model ownership ([4d118a3](https://github.com/pondi/paperpulse/commit/4d118a3531ec82202dc3ff3806a88b48347e0f5b))
* **logos:** reread PostgreSQL image streams ([080db83](https://github.com/pondi/paperpulse/commit/080db83fb39909ad7dad340c48f9508a4848ac02))
* **mail:** escape template substitutions ([6d44242](https://github.com/pondi/paperpulse/commit/6d4424228851331b16fc1bdbe119eaa1af803c51))
* **monitoring:** report worker failures with processing context ([b8b138a](https://github.com/pondi/paperpulse/commit/b8b138a5118c4c3b4cae9b8222a470feb319ad06))
* **notifications:** claim versioned expiry deliveries ([52b64f2](https://github.com/pondi/paperpulse/commit/52b64f21b932537a2208234fe683ad265331b826))
* **notifications:** dismiss popover from bell on Escape ([2a3d997](https://github.com/pondi/paperpulse/commit/2a3d9973f289536c67b3cc7db161cd0e49e329ed))
* **notifications:** fit popover on mobile ([7bf42ae](https://github.com/pondi/paperpulse/commit/7bf42aee70f8aacccf332ceffc12e0ed4c8c4020))
* **notifications:** synchronize bell state and polling ([51db40f](https://github.com/pondi/paperpulse/commit/51db40f12726a592372bffd7211b621e8cf935b8))
* notify owners after Gemini receipt processing ([65575d2](https://github.com/pondi/paperpulse/commit/65575d270acc9ca1e18491dff108c52215588155))
* **ocr:** restore local PDF fallback ([b3931c8](https://github.com/pondi/paperpulse/commit/b3931c838ae07ad26ec5d039b5f556f70e45c185))
* **organization:** alias JSON property address lookup ([7de69a6](https://github.com/pondi/paperpulse/commit/7de69a64298c231557aea88db70ccb829326e8f6))
* **organization:** handle missing JSON property addresses ([9302113](https://github.com/pondi/paperpulse/commit/9302113719d6941d36903e25cd91c724dd8c9b26))
* **organization:** reuse nested folders and restore document previews ([c2cb9ff](https://github.com/pondi/paperpulse/commit/c2cb9ff3e6a3eb83df6385a10ef88be872d9036b))
* paginate scanner object listings ([0ae144a](https://github.com/pondi/paperpulse/commit/0ae144a5731f4b743f39dc40edd152718f36534b))
* persist retryable duplicate cleanup ([5b85db2](https://github.com/pondi/paperpulse/commit/5b85db29b96efa0285a60c1e5005ac30dab50307))
* preserve account asset cleanup ([eff31d3](https://github.com/pondi/paperpulse/commit/eff31d3b0fdba0dcc028030ab5549ce1dfcdcf5c))
* **previews:** fit PDF pages without sidebar ([bdcc8c3](https://github.com/pondi/paperpulse/commit/bdcc8c34027c8f616d9a1238a6679834d83c1735))
* process immutable batch chunks once ([3db2edf](https://github.com/pondi/paperpulse/commit/3db2edfc6b36824eb333c3eb879e97df9b4ce6a9))
* **processing:** report soft and hard failures to Nightwatch ([0532f46](https://github.com/pondi/paperpulse/commit/0532f46a2b5f37fee9fbe2885629600db783cd2b))
* **processing:** report usage limits and stop futile retries ([effe9f5](https://github.com/pondi/paperpulse/commit/effe9f549c8f0200c7c9903256ae4ca632fe73d3))
* **processing:** support bulk Gemini imports and restore recovery ([afba584](https://github.com/pondi/paperpulse/commit/afba58442c9bdc340fca667e6b46afa69e49cc4d))
* **queue:** distinguish ready and delayed jobs ([9089a05](https://github.com/pondi/paperpulse/commit/9089a05b0bbdd130e91e7fa99b7b5e600d6302f0))
* **queue:** prevent overlapping maintenance execution ([2e31175](https://github.com/pondi/paperpulse/commit/2e31175de7bfd6ee4d74337e363e9cda22be45b6))
* **receipts:** allow discarding editor changes ([4e56c36](https://github.com/pondi/paperpulse/commit/4e56c3683c80f3f3e882a03227d87e9dd2e06f8c))
* **receipts:** constrain extracted return deadlines to dates ([718b25d](https://github.com/pondi/paperpulse/commit/718b25dec2b04095d228204f3959ed5807921c29))
* **receipts:** persist edited folder memberships ([a17f94c](https://github.com/pondi/paperpulse/commit/a17f94cb631778a3a89663dd488ca3c56571fb0c))
* **receipts:** persist structured analysis data ([c83979f](https://github.com/pondi/paperpulse/commit/c83979fbcb1f5144a2c604d7d8252dc3365e2af8))
* **receipts:** preserve and reconcile source discounts ([f8fe9dd](https://github.com/pondi/paperpulse/commit/f8fe9dd9c3f0d667a8185d9aaaf3208b8834b13a))
* **receipts:** preserve relative return periods before validation ([46ea73a](https://github.com/pondi/paperpulse/commit/46ea73a8b6430d10793cfaa8eabbb39ca730f279))
* **receipts:** search source file notes ([cbc9742](https://github.com/pondi/paperpulse/commit/cbc97422a22dfc5b9229823d94f2d2eab69f5519))
* **receipts:** show memberships once ([49d98e3](https://github.com/pondi/paperpulse/commit/49d98e33ea4f70497d594cfe5a6b9b7f0d3d3ebf))
* **receipts:** show merchant filter context ([c4230da](https://github.com/pondi/paperpulse/commit/c4230dac9b68f19802976941db32450fa391970f))
* **receipts:** use managed category choices ([58184cd](https://github.com/pondi/paperpulse/commit/58184cdeb4bbd15fae130897035614184b50fc6c))
* **receipts:** wrap descriptions and notes ([42e3cd5](https://github.com/pondi/paperpulse/commit/42e3cd54b978365ac0affef5df2c1b2c3328aff8))
* render stored logos without double encoding ([87a42c7](https://github.com/pondi/paperpulse/commit/87a42c71cdfeab854e0989c4352c45ffdeed89c6))
* replace reprocessed entities atomically by generation ([42e9abb](https://github.com/pondi/paperpulse/commit/42e9abb417bca91cecd97c4251f85b32da773cfc))
* report individual upload outcomes ([b59938d](https://github.com/pondi/paperpulse/commit/b59938d56779b729122f0a7dd3d390d77c5534b7))
* **reports:** distinguish banking empty and error states ([17df1de](https://github.com/pondi/paperpulse/commit/17df1debb08bc37aa1f55ce25b5de370e9b0beb5))
* **reports:** explain omitted calendar months ([9928bdb](https://github.com/pondi/paperpulse/commit/9928bdb63869633e1c9a94ae33789e1721ff8922))
* **reports:** link entities and exact processing records ([3e94a95](https://github.com/pondi/paperpulse/commit/3e94a9529b4a28348fe3e506d04a8a4450e356ca))
* **reports:** render statistic cards ([a815c5e](https://github.com/pondi/paperpulse/commit/a815c5e73969093f91095a4f20c09047bd31e169))
* require PostgreSQL in production ([b804d02](https://github.com/pondi/paperpulse/commit/b804d02fd6bebd8083f7aae4a4f72a5753d51c75))
* resolve canonical file and entity destinations ([53eb250](https://github.com/pondi/paperpulse/commit/53eb2504dd959dc06667af97a52476526ad5013c))
* resolve scoped entity tag bindings ([3bf025b](https://github.com/pondi/paperpulse/commit/3bf025b82bae436f19460cba56ac1ebe6dc32322))
* respect scanner folder boundaries ([da72634](https://github.com/pondi/paperpulse/commit/da7263406a57b1df0beff1e2c8163b105e549d11))
* restore manual static analysis baseline ([112c7f1](https://github.com/pondi/paperpulse/commit/112c7f14aaebf66d606e8bf64991dca5e95925e5))
* reuse validated scanner imports ([4a1a4c2](https://github.com/pondi/paperpulse/commit/4a1a4c203b0bc001780aa14728a577598976d431))
* **runtime:** decouple diagnostics from Forge and verify PostgreSQL behavior ([f95a0ed](https://github.com/pondi/paperpulse/commit/f95a0ede27015490a0ae60eceabb0935e09c134a))
* **runtime:** make Pest caches writable ([36febc3](https://github.com/pondi/paperpulse/commit/36febc394bee1ff8edeca0a090cf9c130d958a2a))
* **scanner:** create import tags once ([a5230c6](https://github.com/pondi/paperpulse/commit/a5230c66766d6aa27952eac8f3ae3739e94fefd1))
* **scanner:** expose camera error recovery ([a5fb1b4](https://github.com/pondi/paperpulse/commit/a5fb1b40935e3b7ed26515f7e011c1c0926e8893))
* **scanner:** recover failures and release image resources ([98a10f5](https://github.com/pondi/paperpulse/commit/98a10f53c74658f2ee2d052c40bb2418a2f6ae6f))
* **scanner:** track durable import claims and results ([d78ec8f](https://github.com/pondi/paperpulse/commit/d78ec8fa442d41873a3b58573562ed5c05b8f941))
* scope bulk document downloads to owner ([7644ab9](https://github.com/pondi/paperpulse/commit/7644ab9efc1cd609f7383a5707ce6b2d57943a81))
* scope scanner root folders by owner ([8d0bc1f](https://github.com/pondi/paperpulse/commit/8d0bc1fb7c7b88bf1bedbbfed5eecd2dbc72d6d4))
* **search:** apply validated engine filters ([928a209](https://github.com/pondi/paperpulse/commit/928a20922c07068bb90944f867bb8515ca86b580))
* **search:** bound and rank Unicode search pages ([121b13a](https://github.com/pondi/paperpulse/commit/121b13af26c7a7417a653610f0d064d5c65f757d))
* **search:** disclose source currency amount semantics ([158b0f7](https://github.com/pondi/paperpulse/commit/158b0f7a1b19df30bf7b19c9dff3c8ed7e11b7bb))
* **search:** expose filter reset and empty recovery ([c469f53](https://github.com/pondi/paperpulse/commit/c469f53ed1d732839576eff0b4f45b389e01d35b))
* **search:** index invoice sender names ([5c5bd5d](https://github.com/pondi/paperpulse/commit/5c5bd5d382c0f7a822210b9eebe7e735bd215ead))
* **search:** label filter and sort controls ([b8b591f](https://github.com/pondi/paperpulse/commit/b8b591fa449562c549cf2ab6aa7dc188cef76f80))
* **search:** reindex all searchable models ([7f51afb](https://github.com/pondi/paperpulse/commit/7f51afb27c17981f3c60baf13a81b6fd777d665b))
* **search:** reindex related and bulk mutations ([e976753](https://github.com/pondi/paperpulse/commit/e976753691b8f0301d0f11a09305e54ac63958fe))
* **search:** separate mobile result metadata ([7531887](https://github.com/pondi/paperpulse/commit/7531887b78cf26f8c06b0584264b4cc4097637cb))
* **search:** sequence requests and expose failures ([84f6e36](https://github.com/pondi/paperpulse/commit/84f6e361fce8d59bea819fc4643a5dd8e87a46ac))
* **search:** support collection engine filters ([8b7c4cf](https://github.com/pondi/paperpulse/commit/8b7c4cfe99accc1a43e3f81db8ed09ced6ed9ebe))
* **search:** version database-backed facet caches ([1ff5c1d](https://github.com/pondi/paperpulse/commit/1ff5c1d3bbfbeca2a1f66a6f97a20e456c684591))
* **settings:** clarify save scopes and pending changes ([7033871](https://github.com/pondi/paperpulse/commit/7033871782b5e731916c55c5195a82eb7c1375e6))
* **settings:** display saved timezone selections ([e1c3d55](https://github.com/pondi/paperpulse/commit/e1c3d5557c06e4cb60f3ec1bf77523835813b7dd))
* **settings:** explain archive budgets and prerequisites ([583d0d9](https://github.com/pondi/paperpulse/commit/583d0d90ff01c3ed021676a218e0b17602b80bf5))
* **settings:** expose common preference sections ([8fad20c](https://github.com/pondi/paperpulse/commit/8fad20cdae533c6fdbd8a34fba7b4f38ffe50445))
* **settings:** retire unused receipt layout preference ([9afc6aa](https://github.com/pondi/paperpulse/commit/9afc6aa3e0b1bb870f9c92dabccfb6c5c4307ce3))
* **tags:** query entity tags by file ID ([8e358f7](https://github.com/pondi/paperpulse/commit/8e358f7843b19b676233f75608bc6f48868be37c))
* **tags:** search names case-insensitively ([f436bcc](https://github.com/pondi/paperpulse/commit/f436bcce4c63ec83955faf98934cc2fa20dc02dd))
* **tags:** use the current file pivot ([4127c9e](https://github.com/pondi/paperpulse/commit/4127c9e90a057c751df504abf5e4fc9f55a30f58))
* **test:** isolate Docker Artisan environment ([43e4ffa](https://github.com/pondi/paperpulse/commit/43e4ffa67017bfdd8f86b4b2c83a39ad54e4a422))
* **tests:** exercise claimed PulseDav jobs ([2b4b2ff](https://github.com/pondi/paperpulse/commit/2b4b2ff4086651bd93170ba2d569ae9cce3acf50))
* **tests:** expect default category labels ([2fa8295](https://github.com/pondi/paperpulse/commit/2fa829590f2a7401ab528069d6db56eecfccfc8c))
* **tests:** force isolated PostgreSQL connections ([f83cd63](https://github.com/pondi/paperpulse/commit/f83cd6354c58414e82c5865a80cc7ffdb3f89af5))
* **tests:** generate unique tag factory names ([9db1e85](https://github.com/pondi/paperpulse/commit/9db1e856543887813634fb273d49d4c9a4073c1a))
* **tests:** reach failed working file writes ([256fa1f](https://github.com/pondi/paperpulse/commit/256fa1f0474b04f6827b5e1ce9ad089a24806900))
* **tests:** submit valid category labels ([44309c5](https://github.com/pondi/paperpulse/commit/44309c5394623ebee1917ed8cfbfdcd5297828ef))
* **tests:** use UUID Gemini processing jobs ([63cd5ca](https://github.com/pondi/paperpulse/commit/63cd5ca9a72e100d498672544ef963d52f8a353c))
* **tests:** use UUID handoff claims ([cbd0a20](https://github.com/pondi/paperpulse/commit/cbd0a20ed13121babef513dd53d08b2dc8dacf4b))
* **tests:** use UUID notification jobs ([4381a87](https://github.com/pondi/paperpulse/commit/4381a87c2d7b6b398bae2d74695d542dc6bffb4a))
* timestamp virtual scanner folders ([980e491](https://github.com/pondi/paperpulse/commit/980e491445309cb930c2d941f11cc72f5206aa22))
* track organization membership changes ([57743b1](https://github.com/pondi/paperpulse/commit/57743b1ca27b9bb7dde6a1f1a6486793238c420a))
* **ui:** distinguish processing review and folders ([bd4a70d](https://github.com/pondi/paperpulse/commit/bd4a70da24a9d8166de4f97cb0fc6bbdf1690569))
* **ui:** expose theme control on mobile ([fceef66](https://github.com/pondi/paperpulse/commit/fceef665b7675cef1ad7a8ababdf596956a389a1))
* **ui:** give pages distinct browser titles ([6ac3805](https://github.com/pondi/paperpulse/commit/6ac380524378e8521a9089bb6f00a09b22050dd7))
* **ui:** honor date and currency preferences ([5e657df](https://github.com/pondi/paperpulse/commit/5e657df5fea5320ce98709e3dea7310bb006cf32))
* **upload:** align picker theme styles ([eec3a86](https://github.com/pondi/paperpulse/commit/eec3a864d31100e5955a5b2a8b5a5b1347ab935b))
* **upload:** explain document classification modes ([f77c5b2](https://github.com/pondi/paperpulse/commit/f77c5b2cc04d576d95698bace4f20aa3ce043823))
* **upload:** remove redundant accepted processing messages ([2a90afe](https://github.com/pondi/paperpulse/commit/2a90afeca69312e49d02396bd460f91f7faa3e06))
* **upload:** style upload outcomes as dismissible alerts ([e573d5c](https://github.com/pondi/paperpulse/commit/e573d5c4e79855e068713be520f07db024db2647))
* **upload:** validate bytes against shared capabilities ([9725e55](https://github.com/pondi/paperpulse/commit/9725e55f0e0d5c9a6650ddb81e333b226b6c9a16))
* use inclusive local expiry dates ([181238a](https://github.com/pondi/paperpulse/commit/181238ac8516f2c56986198e303a5a066d2dd72c))
* **vendors:** show details and purchase history ([fefeab8](https://github.com/pondi/paperpulse/commit/fefeab8841e2657e8a6d05da99d7934566b3b2ca))
* verify isolated Ubuntu office conversion ([dbb0138](https://github.com/pondi/paperpulse/commit/dbb0138cce02aeeca5a98b8f9809eb639fbcc548))
* verify ZIP support in Forge runtimes ([4ed9db5](https://github.com/pondi/paperpulse/commit/4ed9db596c381aa9e16a2a40c1fc25a6cc1edf57))


### Miscellaneous

* constrain storage and PDF dependencies ([96d45b6](https://github.com/pondi/paperpulse/commit/96d45b6b82e54631dd63c1f8e3e2c28175b01f12))
* retire Docker conversion deployment ([eef5ca6](https://github.com/pondi/paperpulse/commit/eef5ca60a912a4198a217c9c7f7309c16c1691c1))
* **runtime:** align setup docs and verification with PostgreSQL ([6d72d7a](https://github.com/pondi/paperpulse/commit/6d72d7ac936ede428b65be3a5e08c911d003f953))
* verify task index links ([6cb1270](https://github.com/pondi/paperpulse/commit/6cb1270f7a301d00700e6b15d47782004211c7e0))


### Documentation

* align native runtime and package guidance ([e45173d](https://github.com/pondi/paperpulse/commit/e45173d6207f963524b62c1b1a70ed9ff3666f56))
* clarify container formatting and migrations ([2613b0d](https://github.com/pondi/paperpulse/commit/2613b0ddad88a10b834d3c72af9f8e16415edbd3))


### Tests

* align file ingestion contracts ([65ae339](https://github.com/pondi/paperpulse/commit/65ae3394e930ad3700422f35ad0cc331428e40bd))
* align search variant response fixture ([25f829b](https://github.com/pondi/paperpulse/commit/25f829b17e3c2d1602016ca021f36007e41df4d8))
* align upload handoff cache contracts ([f9dbf6c](https://github.com/pondi/paperpulse/commit/f9dbf6c3cc3b9d78bc7ba329f3688ada34241ace))
* **auth:** expect email verification after registration ([c72237c](https://github.com/pondi/paperpulse/commit/c72237c094e59448fbbb4bacd7c4a450e6380c69))
* autoload queue job helpers ([df4ec45](https://github.com/pondi/paperpulse/commit/df4ec45c6ee1a336e4e267427f4ef89fc060b866))
* cover current document classifier ([e581d5c](https://github.com/pondi/paperpulse/commit/e581d5c659d0f93206f61c11f188f66878056a41))
* cover organization isolation and lifecycle ([0ba99f9](https://github.com/pondi/paperpulse/commit/0ba99f986487ffc884cd61c8e1e1d5d61b2d15b0))
* cover provider acceptance with mocks ([a3afc1c](https://github.com/pondi/paperpulse/commit/a3afc1c8b36cc964468c64d1a12afc11fefc402f))
* **duplicates:** verify durable cleanup retries ([e949b80](https://github.com/pondi/paperpulse/commit/e949b80a071290470403ee0d60a6d1223923fb18))
* expect decimal CSV line items ([8d182db](https://github.com/pondi/paperpulse/commit/8d182db977d37f94fb4a4d1a26b6416aa305778c))
* **files:** use valid pending cleanup job UUID ([c51da34](https://github.com/pondi/paperpulse/commit/c51da34211e8c29bd37464c5edb44a47765eae67))
* **files:** verify deleted document cleanup ([fd9e9a9](https://github.com/pondi/paperpulse/commit/fd9e9a91feeb7c1c138e991ad7808b6298d63280))
* finish document date pipeline ([5632733](https://github.com/pondi/paperpulse/commit/56327336e7bc15a1e65d8a064d3191d28a5e566c))
* follow configured upload capabilities ([8e9c331](https://github.com/pondi/paperpulse/commit/8e9c3314bf23612441cd81111b304a75a4c603d8))
* isolate trusted proxy fixtures ([fd15c59](https://github.com/pondi/paperpulse/commit/fd15c593d5da0a12b999c7d936618b620671fdd2))
* make manual service checks deterministic ([e53b667](https://github.com/pondi/paperpulse/commit/e53b667066a3603874e8fea0c1b914f3c7f25fd4))
* model stored PDF preview variants ([2642aac](https://github.com/pondi/paperpulse/commit/2642aacf52fa663d259c5c36bd34b1c14bdfaf92))
* **office:** verify isolated conversion and workers ([4d3dccf](https://github.com/pondi/paperpulse/commit/4d3dccf25cf067bfea134d412b014afe100a84ab))
* opt in expiry reminder fixtures ([abcce70](https://github.com/pondi/paperpulse/commit/abcce70a42dd204a8d2236cbf05e50817bcb4d22))
* preserve shared account cleanup originals ([5f34c1c](https://github.com/pondi/paperpulse/commit/5f34c1c126e22dabd7f5c6b468615207559bbed7))
* provide valid OpenAI document response ([d75691a](https://github.com/pondi/paperpulse/commit/d75691a922696e0c67b374f5c9b215deb223aa29))
* **realtime:** cover private channel authentication ([521e677](https://github.com/pondi/paperpulse/commit/521e67701e1e2aca20446b08148e610525f91cfb))
* **realtime:** verify secure Reverb configuration ([c69dead](https://github.com/pondi/paperpulse/commit/c69dead62707731021c180dd2ce575baf7c84d24))
* **receipts:** align ownership and selection fixtures ([1b2dc2a](https://github.com/pondi/paperpulse/commit/1b2dc2ae2f460e62d432da89f742dbc565d2269f))
* record original download fixture path ([e478922](https://github.com/pondi/paperpulse/commit/e4789220a8d3e377d43f6a1486529de3cb0770c2))
* run mocked Gemini processing by default ([5ef5f76](https://github.com/pondi/paperpulse/commit/5ef5f7669fae6e916d65f84c68242e1a1b036ed1))
* verify browser artifact permissions ([1c9becd](https://github.com/pondi/paperpulse/commit/1c9becd13ef40b5fadcdcaf9526977c22915fb5b))
* verify canonical document entity routes ([eda60a3](https://github.com/pondi/paperpulse/commit/eda60a3658fb025e1b2dc7f9b6a8b96295d632dc))
* verify dashboard receipt serialization ([af547e7](https://github.com/pondi/paperpulse/commit/af547e7cb4befa224237b93331db89711fe5f1e4))
* verify durable upload handoff recovery ([610faf0](https://github.com/pondi/paperpulse/commit/610faf06042df7531fcbb405275a037cf59732db))
* verify pending migrations across sessions ([912196c](https://github.com/pondi/paperpulse/commit/912196c20f00bb3595670b9b2a39679eab7c0d9b))

## [1.3.5](https://github.com/pondi/paperpulse/compare/v1.3.4...v1.3.5) (2026-10-01)


### Bug Fixes

* **tests:** remove duplicate PulseDav test case binding ([49b1051](https://github.com/pondi/paperpulse/commit/49b10515b40ee55708b6b056d1048843ff64418e))
* **tests:** resolve Pest bootstrap imports ([116b848](https://github.com/pondi/paperpulse/commit/116b848b34b41e49336e78a071703994ee625b45))
* **tests:** resolve Pest bootstrap imports ([4734cf3](https://github.com/pondi/paperpulse/commit/4734cf39bb67e9545777ba8c695c5c322346f22c))


### Tests

* **pulsedav:** cover folder listing prefixes ([d137b64](https://github.com/pondi/paperpulse/commit/d137b641b4a216bb43be44c9b5b5f1a6c6251e17))

## [1.3.4](https://github.com/pondi/paperpulse/compare/v1.3.3...v1.3.4) (2026-10-01)


### Bug Fixes

* improve document workflows and data integrity ([5e187d9](https://github.com/pondi/paperpulse/commit/5e187d9043e40b75f4830159cf868510054d6e7f))
* **integration:** reconcile supervisor and conversion defaults ([6b0b664](https://github.com/pondi/paperpulse/commit/6b0b6646e3abd41eb972146102cb1cb84d695c52))

## [1.3.3](https://github.com/pondi/paperpulse/compare/v1.3.2...v1.3.3) (2026-09-29)


### Miscellaneous

* **deps:** bump the npm_and_yarn group across 1 directory with 8 updates ([#42](https://github.com/pondi/paperpulse/issues/42)) ([976272d](https://github.com/pondi/paperpulse/commit/976272dab51be5f6410be9608057485c7b7a5636))

## [1.3.2](https://github.com/pondi/paperpulse/compare/v1.3.1...v1.3.2) (2026-09-29)


### Miscellaneous

* **deps:** bump esbuild in the npm_and_yarn group across 1 directory ([#38](https://github.com/pondi/paperpulse/issues/38)) ([1b91588](https://github.com/pondi/paperpulse/commit/1b915881827907009f2a07707c4600f9a60682c6))
* **deps:** bump the npm_and_yarn group across 1 directory with 3 updates ([#41](https://github.com/pondi/paperpulse/issues/41)) ([b8b7312](https://github.com/pondi/paperpulse/commit/b8b73121578969e2cffcff10052bf60bf925b5c1))

## [1.3.1](https://github.com/pondi/paperpulse/compare/v1.3.0...v1.3.1) (2026-06-30)


### Miscellaneous

* prepare for Laravel Forge migration ([72962e3](https://github.com/pondi/paperpulse/commit/72962e30960d6ca143b5e65eed9ea58971c8aaf3))

## [1.3.0](https://github.com/pondi/paperpulse/compare/v1.2.3...v1.3.0) (2026-05-31)


### Features

* add bulk upload API for Uplink desktop client ([7be2ae9](https://github.com/pondi/paperpulse/commit/7be2ae9776f55758fc048c6b5380f5fa5116cb19))
* bulk upload API and full document format support ([2bdcb04](https://github.com/pondi/paperpulse/commit/2bdcb04ec999670aaacd055f3c15b16a1cc31896))
* public collection sharing ([#35](https://github.com/pondi/paperpulse/issues/35)) ([5c2692e](https://github.com/pondi/paperpulse/commit/5c2692eb02545250e1e6c757ed6e4d4e4dc9d8bb))
* support all document formats in API file upload endpoints ([18cded3](https://github.com/pondi/paperpulse/commit/18cded33ce4a15d60116599f163999d759aba0f1))


### Bug Fixes

* add staging environment to Horizon supervisor config ([c4479bb](https://github.com/pondi/paperpulse/commit/c4479bb3c033e06d43d049e198669eba7cb5e6d4))
* add staging environment to Horizon supervisor config ([8a02fb6](https://github.com/pondi/paperpulse/commit/8a02fb669bf896adb4f1d802ebf938fa1a06e7d5))
* eager load logo relationship on Merchant to prevent lazy loading violation ([04b9e95](https://github.com/pondi/paperpulse/commit/04b9e95e0ee9178744126157c4ee72fc0b591220))
* route PHP-FPM error log to stderr for container log visibility ([9b603f3](https://github.com/pondi/paperpulse/commit/9b603f3b6cf9db157395ee29465ab113ec0ff0d3))
* route PHP-FPM error log to stderr for container log visibility ([327bf57](https://github.com/pondi/paperpulse/commit/327bf57806c19e204108a292576a4419b925e54e))
* route PHP-FPM error log to stderr for container log visibility ([30cad1a](https://github.com/pondi/paperpulse/commit/30cad1ad5c4acd96d501a729b6ef32b85aa2428a))
* search 500, tag/collection creation, and file reclassification ([5e36b35](https://github.com/pondi/paperpulse/commit/5e36b35e4ae8a3b8d81d5545d328540c1d54ae61))
* stop classifying emails about invoices as invoices ([4c6de74](https://github.com/pondi/paperpulse/commit/4c6de74d417e98382b265cf2a8f7b771fb0e4618))
* stop classifying emails about invoices as invoices ([cefc255](https://github.com/pondi/paperpulse/commit/cefc2551a38811ff2ba8870c8fc596b733adec08))


### Miscellaneous

* **deps:** bump jspdf from 4.2.0 to 4.2.1 in the npm_and_yarn group across 1 directory ([2781876](https://github.com/pondi/paperpulse/commit/2781876696b82a54de1e90ef7f69f6e81f601822))
* **deps:** bump jspdf in the npm_and_yarn group across 1 directory ([888d468](https://github.com/pondi/paperpulse/commit/888d4688d737a48f111567d146cfaea35aa42c94))
* **deps:** bump socket.io-parser ([#34](https://github.com/pondi/paperpulse/issues/34)) ([301ff0c](https://github.com/pondi/paperpulse/commit/301ff0c1273e2e011045b7eb41616a66c85ed7b4))
* **deps:** bump the npm_and_yarn group across 1 directory with 1 update ([#36](https://github.com/pondi/paperpulse/issues/36)) ([d2ea4b5](https://github.com/pondi/paperpulse/commit/d2ea4b593aa3d0669a1f470f04b6d951421fa8de))
* update deps, docs, and browser test fixtures ([561adaf](https://github.com/pondi/paperpulse/commit/561adaf4bc89cb1b76b1643cda875003aa212eee))

## [1.2.3](https://github.com/pondi/paperpulse/compare/v1.2.2...v1.2.3) (2026-03-10)


### Bug Fixes

* resolve Reverb config at runtime instead of build time ([6a7535e](https://github.com/pondi/paperpulse/commit/6a7535ec528d2cd2f75bc275eec9ea080e5b99f2))
* resolve Reverb config at runtime instead of build time ([5bf7491](https://github.com/pondi/paperpulse/commit/5bf7491131be9522adedb2213fbdd1643c8852a8))

## [1.2.2](https://github.com/pondi/paperpulse/compare/v1.2.1...v1.2.2) (2026-03-10)


### Bug Fixes

* add retry resilience for transient Gemini API errors ([34fd685](https://github.com/pondi/paperpulse/commit/34fd68583ed69a5a85616be6be00b9c237478f93))
* replace varchar reason with jsonb reasons on duplicate_flags ([4207d38](https://github.com/pondi/paperpulse/commit/4207d38baa9bae2f434da78b281b6e70051fe5ab))


### Code Refactoring

* introduce BaseEntityFactory with declarative lifecycle ([9c0ead7](https://github.com/pondi/paperpulse/commit/9c0ead795976b537738e1ea6b51e537d2721f145))

## [1.2.1](https://github.com/pondi/paperpulse/compare/v1.2.0...v1.2.1) (2026-03-10)


### Miscellaneous

* update composer and npm lock files ([d93c7c6](https://github.com/pondi/paperpulse/commit/d93c7c6aa70681a10fe96435021510cd21993d30))

## [1.2.0](https://github.com/pondi/paperpulse/compare/v1.1.0...v1.2.0) (2026-03-10)


### Features

* add automatic document detection to scanner ([ba7472b](https://github.com/pondi/paperpulse/commit/ba7472b60c3e274932e06a16851726dfc7a4187c))
* add breadcrumb navigation to show pages ([8ac42d4](https://github.com/pondi/paperpulse/commit/8ac42d4765dd1fbc0da8643cc21cea1496f0afd3))
* add entity api endpoints ([f149758](https://github.com/pondi/paperpulse/commit/f149758c7fb5e61a7b322862a88b8fb614087c1f))
* add file patch and delete api endpoints ([c0333de](https://github.com/pondi/paperpulse/commit/c0333de7a032dcdf17fe2d4272f661ded4c05603))
* add InvoiceResource api resource ([0033a85](https://github.com/pondi/paperpulse/commit/0033a85e005db5ff55445fa80d73796215d1d97d))
* add job status api endpoint ([5ad9367](https://github.com/pondi/paperpulse/commit/5ad9367b98dd98e6b05acf3015acef417089cb6f))
* add Laravel Dusk browser tests ([9b89fe0](https://github.com/pondi/paperpulse/commit/9b89fe0e1a0964cc51ea168e9441ada07f437c6a))
* add request id tracing across async boundaries ([1399ed2](https://github.com/pondi/paperpulse/commit/1399ed2b5d55854678e6df18817455e00a0de9f5))
* add Reverb WebSocket container for real-time notifications ([d18f6d0](https://github.com/pondi/paperpulse/commit/d18f6d05fc028c2573014b481b4fd4eea081533f))
* add Tags & Collections API endpoints and Scanner PWA ([da13dc1](https://github.com/pondi/paperpulse/commit/da13dc13ae0644eb21e17dad5082101124550605))
* **admin:** add admin guard and navigation updates ([88e69ee](https://github.com/pondi/paperpulse/commit/88e69ee249cf5b3f1848e760abf5730a001661ac))
* **ai:** add gemini processing pipeline ([2ad93ca](https://github.com/pondi/paperpulse/commit/2ad93ca40a47da714a2bbedb641abb87ddd659d3))
* **ai:** add gemini provider + prompts ([6eacfd5](https://github.com/pondi/paperpulse/commit/6eacfd549e584243f285fba494addd9964d4dde5))
* **ai:** improve service, prompts, and tooling ([555f92b](https://github.com/pondi/paperpulse/commit/555f92bdd267f02bfc22f5fddaa22f1c0ae3507c))
* **ai:** match outputs to document language ([e5e79e5](https://github.com/pondi/paperpulse/commit/e5e79e5bc62dcc5bd3845e807a292db397d2cc5e))
* **analytics:** add processing analytics ([6a2b3d2](https://github.com/pondi/paperpulse/commit/6a2b3d26b2b6ac4b8b95861ccdbeb7d02e669566))
* **api:** add filtered file list response ([241a534](https://github.com/pondi/paperpulse/commit/241a5344408a455f2e5e084c4334a61d6d96c8e9))
* **api:** add v1 search and file streaming ([80f6c8b](https://github.com/pondi/paperpulse/commit/80f6c8bf30d8f7c1afbf02ab84d54ff022db80e9))
* **api:** return file detail with receipt/document data ([74163f3](https://github.com/pondi/paperpulse/commit/74163f31e8b15fcca106a14f0cf9bbf533075ba3))
* **bank-statements:** add bank statement & CSV import feature ([ac355fe](https://github.com/pondi/paperpulse/commit/ac355febefa8e0f844d4a90a4daa4cefd07de604))
* **bank-statements:** add model + api ([a0a2af7](https://github.com/pondi/paperpulse/commit/a0a2af79ba877fcec11ec444dbb95801164a7772))
* **categories:** expand default categories ([d3859e4](https://github.com/pondi/paperpulse/commit/d3859e4b52b533a101ea9350574c33f7cd64156e))
* **classification:** add gemini type classifier ([5acc8ba](https://github.com/pondi/paperpulse/commit/5acc8baf395ce6552dee2ed5da53377ad59273eb))
* **collections:** add collections system ([5610887](https://github.com/pondi/paperpulse/commit/56108874e512da1253ed3b9f4fea9383e571d7fe))
* **contracts-ui:** add contract pages ([2d9683b](https://github.com/pondi/paperpulse/commit/2d9683b0c11b1381e90bdb67de121f5120529ce4))
* **contracts:** add model + api ([4bc6889](https://github.com/pondi/paperpulse/commit/4bc6889b4c9d75adc08b1aaa23e1f9e7e551e08e))
* **deploy:** adding build assets ([bdefc9e](https://github.com/pondi/paperpulse/commit/bdefc9e4c451745f358e8ce0d5c5aa8f47da8f72))
* **documents:** add model, storage, HTTP, and UI ([d0d7359](https://github.com/pondi/paperpulse/commit/d0d73595480701bd414e38c8856e32f6e11bde47))
* **documents:** redirect entities and add file previews ([8ff6665](https://github.com/pondi/paperpulse/commit/8ff666506058560f36fdd0b6258310fe8662c7f1))
* **documents:** replace menu with action icons ([1e32b7c](https://github.com/pondi/paperpulse/commit/1e32b7c96a9284032019904df56a3c36c5be379c))
* **duplicates:** add duplicate flags + ui ([cce39c6](https://github.com/pondi/paperpulse/commit/cce39c6963bed78aaeec7b5f25f6c8fbb075aaf4))
* enhance health check with component status ([ec08d56](https://github.com/pondi/paperpulse/commit/ec08d56b8d17ca05d9279bb1d83cd39f66498c9f))
* **extractable:** add base extractable entity infra ([38bc985](https://github.com/pondi/paperpulse/commit/38bc985567f6dfd97abb2da9f612c7d29713e02c))
* **extractors:** add bank statement extractor ([6c67a13](https://github.com/pondi/paperpulse/commit/6c67a13df9cf6b5e6345d18c546a22dab03e84d3))
* **extractors:** add contract extractor ([300c3bc](https://github.com/pondi/paperpulse/commit/300c3bc9f729eb52d9106cf3bca6f3ce546bfe91))
* **extractors:** add document + receipt extractors ([0c0fe7e](https://github.com/pondi/paperpulse/commit/0c0fe7ed202bc7fed6c536063c3847b8c7737f1f))
* **extractors:** add invoice extractor ([3cada26](https://github.com/pondi/paperpulse/commit/3cada26701360c8c8f86182239ce21a94b5d67cf))
* **extractors:** add voucher + warranty extractors ([27e4f92](https://github.com/pondi/paperpulse/commit/27e4f92bf54987d570eadc1791641ecc9af80127))
* **file-details:** show extracted entity cards ([157c968](https://github.com/pondi/paperpulse/commit/157c968c51e057c0b775a2d5685c723ccda7e395))
* **files:** add configurable pagination to files-processing page ([c157dd7](https://github.com/pondi/paperpulse/commit/c157dd7df91e58bdd00ea90e1aef03ebab16e2f3))
* **files:** add primary receipt/document helpers ([0c3973b](https://github.com/pondi/paperpulse/commit/0c3973b549e4a0c13f1764277c8eb62bbaa74976))
* **files:** expand file management statuses and URL ([4961c91](https://github.com/pondi/paperpulse/commit/4961c91e1355bb35151f7fccfe51041b7b5280ea))
* **files:** expand ingestion and OCR pipeline ([6f67975](https://github.com/pondi/paperpulse/commit/6f679759fbbd6b05a14a4ca45f1c71b39ce66f2e))
* **files:** implement SHA-256 deduplication and enhance notifications ([57a9004](https://github.com/pondi/paperpulse/commit/57a900445c5c553de45950afee55cfe3682c4bea))
* **files:** improve processing, reprocessing, and jobs ([14c7f33](https://github.com/pondi/paperpulse/commit/14c7f3368e536f919042cd6551176d4fc395266b))
* **files:** manage failed file reprocessing ([226e158](https://github.com/pondi/paperpulse/commit/226e158b3cc398daed313b8bf853b0ac54dc5395))
* **files:** rename converted PDFs to archive variant ([3fbbb12](https://github.com/pondi/paperpulse/commit/3fbbb129d406ff0a4662be444387b23da03b5a0e))
* **frontend:** update layouts, navigation, and shared components ([e70f6c2](https://github.com/pondi/paperpulse/commit/e70f6c23f076e48162af0f7b34b296ecd6504eba))
* implement web-based document scanner ([cd349d5](https://github.com/pondi/paperpulse/commit/cd349d5cba27546662fb203a59d8a2cf6eb759b0))
* **invites:** modernize invitation intake ([e34fa2a](https://github.com/pondi/paperpulse/commit/e34fa2addbf5c832ec4da67bd90740624ed7bf1c))
* **invoices-ui:** add invoice pages ([f36414b](https://github.com/pondi/paperpulse/commit/f36414b7817de21b5ea8d7b2d0b07fbc37450b9c))
* **invoices:** add model + api ([7ee0721](https://github.com/pondi/paperpulse/commit/7ee07217175683965859efff767f4a5b3a8cd6c7))
* **jobs:** enhance job history with pagination and clickable stats ([21c4470](https://github.com/pondi/paperpulse/commit/21c44704d8077b55c72bd9327a94793907923ce7))
* **jobs:** harden monitoring and restarts ([251c85b](https://github.com/pondi/paperpulse/commit/251c85bc7fa317edafa25c668c0fb681a3cf7c0e))
* make category slug unique per user instead of globally ([bba849f](https://github.com/pondi/paperpulse/commit/bba849f141ecbad64cdd1484e38ff0cd9e8aea1a))
* migrate CSP to spatie/laravel-csp ([606a89f](https://github.com/pondi/paperpulse/commit/606a89f27cc9c90b0db5fad346a91493abe5c526))
* migrate CSP to spatie/laravel-csp with nonce support ([bd1916a](https://github.com/pondi/paperpulse/commit/bd1916aa705976d44c6986fd34d2ebf345f15be8))
* **notifications:** add expiring alerts + widgets ([2669033](https://github.com/pondi/paperpulse/commit/2669033b34138aaf9b3ed22a1dc3417ddf8d6a38))
* **ocr:** improve Textract processing and artifacts ([073f6a5](https://github.com/pondi/paperpulse/commit/073f6a573df33be85dbd29feab24cc5bc12f060e))
* **policies:** add DuplicateFlagPolicy ([cb4838d](https://github.com/pondi/paperpulse/commit/cb4838d9a940e876556372ed7e607b0a838d4a9f))
* **pulsedav:** add select all folder files ([b484574](https://github.com/pondi/paperpulse/commit/b4845746a8f5bd3b09be2e5d1b6cc5ec9c527e95))
* **receipts:** enhance analysis, merchants, and dashboard ([b61f9ac](https://github.com/pondi/paperpulse/commit/b61f9acd227fa04915cb1c8df8b3cde91a4e59de))
* **receipts:** map category + description in extraction ([1987db7](https://github.com/pondi/paperpulse/commit/1987db76361b295b8a54a46fbf62e533f16bbf28))
* **receipts:** replace summary with description ([3c93509](https://github.com/pondi/paperpulse/commit/3c9350978329998a219c9c2300ac6c6e4c48d36a))
* **release:** v1.0.0 ([#12](https://github.com/pondi/paperpulse/issues/12)) ([e829bed](https://github.com/pondi/paperpulse/commit/e829bed5f676806f552c4f1288799e164f352b4d))
* **requests:** add BulkReceiptIdsRequest ([f0efc4a](https://github.com/pondi/paperpulse/commit/f0efc4ac9f293f27eb37614b96facd25be14de28))
* **requests:** add CollectionFilesRequest ([39d0bef](https://github.com/pondi/paperpulse/commit/39d0befa08a6469192e55b8a1a11cb3d06dd7d96))
* **requests:** add ShareCollectionRequest ([81f5fc1](https://github.com/pondi/paperpulse/commit/81f5fc1f1f2b639ae0f01f1c1ace82d3f8a1d277))
* **requests:** add StoreCategoryRequest ([82273c4](https://github.com/pondi/paperpulse/commit/82273c44a6830354faf0ecf62b0da593724f2de3))
* **requests:** add StoreCollectionRequest ([6f759bd](https://github.com/pondi/paperpulse/commit/6f759bd4fa21e83cef628ca9a2e7b56981fd6c7a))
* **requests:** add StoreLineItemRequest ([f61f5f7](https://github.com/pondi/paperpulse/commit/f61f5f70d8daeea1a334609b05b836f930e046ef))
* **requests:** add StoreTagRequest ([fca95bd](https://github.com/pondi/paperpulse/commit/fca95bd378b75aeace6eb811ba9ac22205ae8b1e))
* **requests:** add UpdateCategoryRequest ([b3efa7d](https://github.com/pondi/paperpulse/commit/b3efa7dc6b28e4db421ab028c7aaa4e0a96c2d12))
* **requests:** add UpdateCollectionRequest ([184d272](https://github.com/pondi/paperpulse/commit/184d272defe0d024a1c25c278fb93d0f993cea6f))
* **requests:** add UpdateTagRequest ([e99eeb1](https://github.com/pondi/paperpulse/commit/e99eeb11b5c7e5f98dcb27be68cdf4ea4ebe931d))
* **resources:** add ContractInertiaResource ([eb91624](https://github.com/pondi/paperpulse/commit/eb91624b58f72696b41e8d2dde49cf3c3928eb5f))
* **resources:** add DocumentInertiaResource ([5d6f4a1](https://github.com/pondi/paperpulse/commit/5d6f4a15dbfa58e7f4850be5bbcd5683f74cd79d))
* **resources:** add DuplicateFlagInertiaResource ([c170abd](https://github.com/pondi/paperpulse/commit/c170abd3a1f0f5f531411cdb469cfafdb7c59304))
* **resources:** add FileInertiaResource ([f4d4745](https://github.com/pondi/paperpulse/commit/f4d47450dfb37da83b640c54abae95b17dd0ac65))
* **resources:** add InvoiceInertiaResource ([5005887](https://github.com/pondi/paperpulse/commit/500588798985ce447e1b4fd6301000639e399511))
* **resources:** add JobHistoryInertiaResource ([0c03c58](https://github.com/pondi/paperpulse/commit/0c03c58fe5d9a02b3b66a221d4b0358d4697fad8))
* **resources:** add ReceiptInertiaResource ([3621d6b](https://github.com/pondi/paperpulse/commit/3621d6b9c41e7832641c7a850a236ec58a417bf5))
* **resources:** add VoucherInertiaResource ([c94d717](https://github.com/pondi/paperpulse/commit/c94d71722db40003992209c42b9c5be3f296e83c))
* **return-policies:** add model + api ([cd05758](https://github.com/pondi/paperpulse/commit/cd0575830388ce20d8485b7cd51048862be0b443))
* **search:** add unified receipts/documents search ([f6b7788](https://github.com/pondi/paperpulse/commit/f6b778890c2c1e677927bd583e6662c5c3b6ec30))
* **search:** add unified search service ([ccfceb0](https://github.com/pondi/paperpulse/commit/ccfceb04656d460b704ee3c8dcbf9e383f712080))
* **search:** implement natural language OR search with multi-word ranking ([121e963](https://github.com/pondi/paperpulse/commit/121e9639f2b36570300606a8e3749e5c398b85ab))
* **sharing:** refine tags, sharing, and PulseDav ([acee306](https://github.com/pondi/paperpulse/commit/acee306e70c19f0e1993579c543b473722b6e588))
* switch notifications from polling to Laravel Echo with Reverb ([3dfe263](https://github.com/pondi/paperpulse/commit/3dfe2636da8d40b4360d01520b2cc8892e0f9161))
* switch notifications to Laravel Echo with Reverb ([bde85bc](https://github.com/pondi/paperpulse/commit/bde85bc428ed90760db806ddcd1b1c319382225b))
* **ui:** redesign invoice and contract views ([f722c82](https://github.com/pondi/paperpulse/commit/f722c82411c948517aa239a443622908e5af5139))
* **ui:** refresh layout and emails ([93a6cee](https://github.com/pondi/paperpulse/commit/93a6cee398cea6e98af58083cb9369dcb0e94d7e))
* **upload:** add file upload sizing rules ([b7e7017](https://github.com/pondi/paperpulse/commit/b7e7017ba254555d344792741ffed1319ade9480))
* **vouchers-ui:** add voucher pages ([d0111b7](https://github.com/pondi/paperpulse/commit/d0111b7d6cb2108bf4318d1c899e7312f45c1979))
* **vouchers:** add model + api ([62c3fc0](https://github.com/pondi/paperpulse/commit/62c3fc08b30059797fef293d66336595ad43ecf4))
* **warranties:** add model + api ([2d2a686](https://github.com/pondi/paperpulse/commit/2d2a686457d443273e315eb02034b336b0de7601))


### Bug Fixes

* add 30-day expiration to API tokens ([605b61b](https://github.com/pondi/paperpulse/commit/605b61b9b6d6d9a612a77570ff10ffbd6346e462))
* add compound soft delete indexes to 18 tables ([27e6472](https://github.com/pondi/paperpulse/commit/27e647262b33050aaa1f4a289a3810463e4a3797))
* add CSP nonce to inline scripts ([9d7492c](https://github.com/pondi/paperpulse/commit/9d7492c79c27cffab2d659694d8f97528502076c))
* add imagick to Reverb build stage for Composer platform checks ([367e1a3](https://github.com/pondi/paperpulse/commit/367e1a3257330e9cb419bc9c08629e03ab650f29))
* add int type declarations to job timeout/tries properties ([646141b](https://github.com/pondi/paperpulse/commit/646141b6ffd7d8733adef147274cae63a63e9bb0))
* add job idempotency checks and standardize timeout/retry config ([a367cf3](https://github.com/pondi/paperpulse/commit/a367cf37c608f9509e8bd7e905661ae997ca925c))
* add missing declare(strict_types=1) to GeminiProvider ([211d148](https://github.com/pondi/paperpulse/commit/211d148c0bacc56120c02369c7517ccb8b03c37f))
* add rate limit to login endpoint ([a0b0d9a](https://github.com/pondi/paperpulse/commit/a0b0d9a3b21905e44f427e408d9aafee7a906522))
* add user_id to bank_transactions with backfill migration ([4dd0eed](https://github.com/pondi/paperpulse/commit/4dd0eed20f4669fe681d0ddcd612da78db6e1cdf))
* **ai:** resolve gpt-5.2 integration and token limit issues ([cded084](https://github.com/pondi/paperpulse/commit/cded084e94318f0f9adc429fb5ae337561ef031b))
* **ai:** update to GPT-5 API compatibility ([4562693](https://github.com/pondi/paperpulse/commit/4562693eaca3621eb780428444c40c7d5fd8170e))
* cleanup during reprocessing ([50bdc86](https://github.com/pondi/paperpulse/commit/50bdc86d4959cc5d4af3169c1ef9f04f11e0d88f))
* codebase audit cleanup ([5328a98](https://github.com/pondi/paperpulse/commit/5328a98d73a3347f2f7fa316d25e8f573d0cc1f5))
* **controllers:** serialize Inertia resources to arrays ([fdfc5c2](https://github.com/pondi/paperpulse/commit/fdfc5c2caa11054a5e53f9aea3edcd57c75e15f9))
* **db:** align file share schema and db config ([ddb8953](https://github.com/pondi/paperpulse/commit/ddb8953e1160a3f4a9f3223ecc88d5ed59c3b97d))
* **design:** align design gaps ([7367122](https://github.com/pondi/paperpulse/commit/736712263a454d349534d4c4bd391eaefc28c8cf))
* **documents:** align show response types ([41aa0e1](https://github.com/pondi/paperpulse/commit/41aa0e102c45c4d7c7612d5d3c712c4a8d834f42))
* **documents:** allow redirects from show ([b68a21e](https://github.com/pondi/paperpulse/commit/b68a21e65c23093d8c6ac81863dad97642c5c518))
* **documents:** complete invoice contract voucher views ([eb49605](https://github.com/pondi/paperpulse/commit/eb496057939be2933ba0c5d645125375f451f924))
* eager load child relations in FileEntityCleanupService ([0f079df](https://github.com/pondi/paperpulse/commit/0f079dfc45edf01da59eda15110e63c266fdc7a0))
* enable preventLazyLoading in non-production to catch N+1 queries ([83e49de](https://github.com/pondi/paperpulse/commit/83e49deebb419ec63daf67b6696c58db482364a5))
* **files:** clean up orphan file records ([5989026](https://github.com/pondi/paperpulse/commit/5989026ae9bd259ca1fe10797c61bd9eb0249fd9))
* **files:** preserve original file dates from scanner imports ([cb66118](https://github.com/pondi/paperpulse/commit/cb66118ea3d16bcb722a198920564daf2196f8cf))
* **files:** prevent watch from firing on initial mount ([ad8f66b](https://github.com/pondi/paperpulse/commit/ad8f66bf1cca6466c3534a181527acaa9b819580))
* fixing various bugs ([56a60d4](https://github.com/pondi/paperpulse/commit/56a60d4d8290192a41bfce98774dbc51d8d0d7db))
* **gemini:** Remove unsupported schema properties ([d193190](https://github.com/pondi/paperpulse/commit/d193190a8bd16ff7ac1d1b41dd3ab5f0c409383f))
* handle race condition in category creation ([23cf53a](https://github.com/pondi/paperpulse/commit/23cf53a565735c16a6d94ea3143142f23fc19d04))
* improve accessibility with aria labels, focus trap, and keyboard nav ([299b0fe](https://github.com/pondi/paperpulse/commit/299b0fe19b842103edf9ed563295a6f83b9dc45c))
* improve Gemini timeout error handling and categorization ([8d2e80c](https://github.com/pondi/paperpulse/commit/8d2e80c52b351ce0ce69ffcb3de04847ccf86709))
* invalidate search facets cache on entity mutations ([4764a85](https://github.com/pondi/paperpulse/commit/4764a852dd02633286da6a38cea3bf7a3f14078f))
* **jobs:** guard failure persistence and sanitize document text ([feb8015](https://github.com/pondi/paperpulse/commit/feb8015311b7b9e05e0093f1e491c8dd1d66cb49))
* **policies:** use OwnedResourcePolicy for entity types ([64d37be](https://github.com/pondi/paperpulse/commit/64d37bec66c9209f9ea85743821e4eed4a121444))
* PostgreSQL boolean type compatibility across all models ([b1ccb55](https://github.com/pondi/paperpulse/commit/b1ccb55db3b8e577daea4e773253a160b3db7c40))
* prevent duplicate failure handling in BaseJob ([a9caa06](https://github.com/pondi/paperpulse/commit/a9caa0607c1c5047c11282af960a8cc4fdb9b123))
* **pulsedav:** add document_id to pulsedav_files ([a6765c0](https://github.com/pondi/paperpulse/commit/a6765c026c5e5f994b225ea83dbcb78ea0fbc181))
* **pulsedav:** resolve race condition in UpdatePulseDavFileStatus job ([6eff1a8](https://github.com/pondi/paperpulse/commit/6eff1a85dce010104d15de554858c10d35c044b0))
* **receipts:** allow processing without merchant information ([a07078e](https://github.com/pondi/paperpulse/commit/a07078e965819c961d489c9c84202326e2aa4f62))
* redact sensitive fields from validation errors in production ([ea029f1](https://github.com/pondi/paperpulse/commit/ea029f177ab4e9aafe4cb3b5a7efe1e4034efd49))
* remove defensive fallbacks and fix delete errors ([aeabf66](https://github.com/pondi/paperpulse/commit/aeabf6657b3b31a1313f2a7d60c517a0aa6ebb43))
* remove redundant make method override ([077fa1d](https://github.com/pondi/paperpulse/commit/077fa1d3fe5d353a9b07ea7176e26bedd3c06459))
* replace exception message leakage with generic error messages ([fbd151b](https://github.com/pondi/paperpulse/commit/fbd151b6eec187cb76fef1bf4e58bbca744e3627))
* resolve categories outside transaction to prevent PostgreSQL abort ([7924fc7](https://github.com/pondi/paperpulse/commit/7924fc7ed8748c5339eac9765fc802a65411b917))
* sanitize v-html in search results and pagination ([f6262b5](https://github.com/pondi/paperpulse/commit/f6262b5ba5b22bd2c90c0388b067eecc32cdeead))
* **scanner:** improve detection and capture ([a6dcabf](https://github.com/pondi/paperpulse/commit/a6dcabf3837b7e6206b6bc9271a0ca1360304aa4))
* scope TestCase binding to Unit/Services and Unit/Jobs subdirectories ([94e92d2](https://github.com/pondi/paperpulse/commit/94e92d289bd7b1e05b19495ccf98aa6839dc8bae))
* **search:** inconsistencies in search ([ce6b2e5](https://github.com/pondi/paperpulse/commit/ce6b2e54f8fae515fff5880584e4e0ac4dc4f07d))
* **security:** add authorization checks and improve API error responses ([185f759](https://github.com/pondi/paperpulse/commit/185f759264638d13631876ab6413c980fa9ec514))
* standardize api error response codes ([5ad9731](https://github.com/pondi/paperpulse/commit/5ad97317887bcd96525cda06697b30afd0cbafb9))
* stream bulk CSV export with chunking to prevent memory exhaustion ([cd4d52d](https://github.com/pondi/paperpulse/commit/cd4d52daac4a375ad5dcf7018f34fe23eab57668))
* tighten CSP with nonce-based script-src, drop unsafe-eval/inline ([70ea532](https://github.com/pondi/paperpulse/commit/70ea5322e056561f09cbf5625900c26104c4283d))
* update bank statement tests for user_id scoping ([7d2257b](https://github.com/pondi/paperpulse/commit/7d2257b5d7965a7f1a3ee2fef43ad0c36ba71c4b))
* update TagController to use files relationship, add PgBouncer support ([42079f4](https://github.com/pondi/paperpulse/commit/42079f416d8f492a07d77d92505e4a74bc920328))
* use composite keys for v-for loops to prevent reactivity issues ([621010d](https://github.com/pondi/paperpulse/commit/621010d7ba680532e6b6ebe261bf50737a288f49))
* use raw SQL boolean for PostgreSQL compatibility in primaryEntity ([1db44b5](https://github.com/pondi/paperpulse/commit/1db44b5dca534a01823f69513bd22c02d0f663fd))
* use savepoint in resolveOrCreateCategory for PostgreSQL compatibility ([6cb0b8d](https://github.com/pondi/paperpulse/commit/6cb0b8d6a62626e9443df6543615c967ff40eb0c))
* wrap FileProcessingService and DuplicateController in DB transactions ([86084a1](https://github.com/pondi/paperpulse/commit/86084a12dee7aa798e944f7e38a01a958fe8088f))


### Miscellaneous

* add nightwatch support ([fa17b96](https://github.com/pondi/paperpulse/commit/fa17b96e2aeb52a435b269d61c5fd3e650559d92))
* add release-please workflow and README development banner ([cdc5ebd](https://github.com/pondi/paperpulse/commit/cdc5ebd0adf934fcd45d57681cf0227a07cd5249))
* **analysis:** update phpstan baseline ([183a01d](https://github.com/pondi/paperpulse/commit/183a01d99d56b8a5c2fabfbaeb748a13d0e8688b))
* code cleanup, security hardening, and test fixes ([e36f4c7](https://github.com/pondi/paperpulse/commit/e36f4c7c425d0ed75584fdfc22993ed867d347b3))
* **config:** add boost config ([ddceda2](https://github.com/pondi/paperpulse/commit/ddceda268cf5f0cd6106bad8717ae54cd6286f22))
* **config:** refresh env and core config ([98a3ef5](https://github.com/pondi/paperpulse/commit/98a3ef54fa497660f7907af6471694b66af43f6d))
* **deploy:** remove k8s folder ([c7a174b](https://github.com/pondi/paperpulse/commit/c7a174b285156b0c57ed7c72fbdd61a99e72463c))
* **deps:** bump frontend tooling ([000c398](https://github.com/pondi/paperpulse/commit/000c39882dddf2d6ada69a20b33beec717292572))
* **deps:** bump jspdf in the npm_and_yarn group across 1 directory ([#15](https://github.com/pondi/paperpulse/issues/15)) ([b3442d4](https://github.com/pondi/paperpulse/commit/b3442d4902277beff018427344f51d855df25008))
* **deps:** revert frontend tooling to Tailwind v3 ([8933528](https://github.com/pondi/paperpulse/commit/893352832f7f35d1c33250cfe4c3ef3c7462c48d))
* **dev:** add gotenberg/converter compose ([1ca731e](https://github.com/pondi/paperpulse/commit/1ca731e00aa6176754d624506e1d0b986c0247ed))
* **docker:** add dockerignore ([f8a74a3](https://github.com/pondi/paperpulse/commit/f8a74a3f97343670bd80bff961385613c575aafb))
* **docker:** expand ignore rules ([a37df7d](https://github.com/pondi/paperpulse/commit/a37df7d2d4c22939bad4b82a0a3202399ae487ba))
* gitignore .issues directory ([c6932c6](https://github.com/pondi/paperpulse/commit/c6932c6413f3a0c9e8c8e77027dc94296978997f))
* **gitignore:** ignore local build/push helper ([9564d39](https://github.com/pondi/paperpulse/commit/9564d39c6593c84605d34366df0199962660bb4b))
* **http:** refine middleware and service wiring ([1f85341](https://github.com/pondi/paperpulse/commit/1f8534177ffcc640167872f5acd4aa24503ef92d))
* **logging:** reduce tag job verbosity ([d51c992](https://github.com/pondi/paperpulse/commit/d51c99261fa92f95d30f22ef004e1ab252f65cd6))
* **logging:** treat empty env vars as unset ([c7ff8d8](https://github.com/pondi/paperpulse/commit/c7ff8d8daafc42a68e547e86470a6f51586284ea))
* **packages:** update ([1953cdd](https://github.com/pondi/paperpulse/commit/1953cddb719fc6d97fab15686e5616993b036a93))
* **packages:** upgrades ([dd24121](https://github.com/pondi/paperpulse/commit/dd241214b5d9594dfcd8fd1170f0e910220b645e))
* remove ContractTransformer ([d984eeb](https://github.com/pondi/paperpulse/commit/d984eeb3c7a47693eea443e252395b0f94500416))
* remove DocumentTransformer ([c5e05b8](https://github.com/pondi/paperpulse/commit/c5e05b83065c0682784c0c5b0c3819257a46f33b))
* remove DuplicateFlagTransformer ([2783575](https://github.com/pondi/paperpulse/commit/27835758b78b41035d4dae483e293db92216387d))
* remove FileTransformer ([a687052](https://github.com/pondi/paperpulse/commit/a687052bd2e7b9307c4414bc59aa264bbf9e2f16))
* remove InvoiceTransformer ([2971323](https://github.com/pondi/paperpulse/commit/29713233bc8ca57058d3da1af839bf3e237b4ee1))
* remove JobHistoryTransformer ([ab76bec](https://github.com/pondi/paperpulse/commit/ab76becd222628e8375e32d537b5802ea8b208ab))
* remove ReceiptTransformer ([7ffb61f](https://github.com/pondi/paperpulse/commit/7ffb61f2cc02e69b82550295a8bab382710479ba))
* remove trailing newline ([6f39e79](https://github.com/pondi/paperpulse/commit/6f39e79c8e96decf756097f6668d65d78561a037))
* remove Vite cache directory (.vite) from repo ([63a8c64](https://github.com/pondi/paperpulse/commit/63a8c645c442d032ec54b59a0e85a18a3ee1dd6c))
* remove VoucherTransformer ([e0c8a02](https://github.com/pondi/paperpulse/commit/e0c8a020c28a9537d274f87962246702f7140db8))
* run pint across full codebase ([71ce521](https://github.com/pondi/paperpulse/commit/71ce52137491c18b0c89fcc363407eef4c74b77e))


### Code Refactoring

* **ai:** update all models to gpt-5-mini via config ([61ae571](https://github.com/pondi/paperpulse/commit/61ae571ca593aded605e2d28302969bdb50309ed))
* aligned with inertia ([182b6fa](https://github.com/pondi/paperpulse/commit/182b6fa3c450e89368fcac67a6e9bba376d8e2d4))
* **api/collections:** use form requests ([60c021e](https://github.com/pondi/paperpulse/commit/60c021e16b26348f1910b1adc5e5d24fec8b1378))
* **api/collections:** use policy auth ([318d37a](https://github.com/pondi/paperpulse/commit/318d37ac3a8a941c8a05793e40652cfaf7f6bd4f))
* **api/duplicates:** use Inertia resource ([3165263](https://github.com/pondi/paperpulse/commit/3165263610f77be9a23079c22372a4960b582f3e))
* **api/duplicates:** use policy auth ([b25b184](https://github.com/pondi/paperpulse/commit/b25b1848f6fb6d144546638f1e4d16ce82a118b0))
* **api/files:** use policy auth ([14d7c69](https://github.com/pondi/paperpulse/commit/14d7c6982d7c06c48a72efbac5771a322cf6fb0a))
* **basecontroller:** refactor and fix documents structure ([de77fa0](https://github.com/pondi/paperpulse/commit/de77fa0acb117bb84b2635577f8979b70df72dfd))
* **bulk:** use form requests ([607308f](https://github.com/pondi/paperpulse/commit/607308f832ad1e4b478f2dd41d7ce3dec76f36a9))
* **categories:** use form requests ([7a63bc8](https://github.com/pondi/paperpulse/commit/7a63bc8050fab950ed58d6c01f275aaa09b8a7e4))
* **cli:** consolidate and clean up console commands ([caef75f](https://github.com/pondi/paperpulse/commit/caef75fbbb386c0f1d2d8708088a3822cc3eedeb))
* **collections:** use form requests ([7690aed](https://github.com/pondi/paperpulse/commit/7690aedadeb2e8c7cff5ef48bc8eefd68e7193d2))
* **contracts:** use Inertia resource ([e2dfdf2](https://github.com/pondi/paperpulse/commit/e2dfdf2a242b95360cdca308e0dcf057c56263f2))
* **documents:** use Inertia resource ([a214ac4](https://github.com/pondi/paperpulse/commit/a214ac46e5e6a8b007911cc18c00458ced35f1b0))
* **duplicates:** use Inertia resource ([a4b1922](https://github.com/pondi/paperpulse/commit/a4b1922e10fad1c1b2bbfc57ed2a3c3decbc9ee9))
* extract BaseEntityApiController to DRY 6 entity API controllers ([eda28fa](https://github.com/pondi/paperpulse/commit/eda28faef94c32d3fe65b9f8b195910f81e2bd98))
* extract hasAny() to ChecksDataPresence trait ([8494f0d](https://github.com/pondi/paperpulse/commit/8494f0d15fec148d60ba460466246a4095d7ede7))
* **files:** use Inertia resource ([dd4e1a9](https://github.com/pondi/paperpulse/commit/dd4e1a9bd1fcb3b8eafc1eb0aaa44b745665a073))
* improve data architecture for reprocessing and user privacy ([91a7050](https://github.com/pondi/paperpulse/commit/91a7050fd20a01fc25ec9f3ce2815627d6fa890e))
* improve scanner detection and layout ([17dc3c3](https://github.com/pondi/paperpulse/commit/17dc3c36a30b9382cdb2508960eeab7f89026c56))
* **invoices:** use Inertia resource ([7a77bb3](https://github.com/pondi/paperpulse/commit/7a77bb3bd23ee60921183bdf2d5b42cc82097cb0))
* **jobs:** use Inertia resource ([74cf151](https://github.com/pondi/paperpulse/commit/74cf1515710ada4b67c88479eb72ededff70d49e))
* let Laravel handle date serialization ([dd9e090](https://github.com/pondi/paperpulse/commit/dd9e090145a0a74cc65a4b39d39b8505245c8927))
* **line-items:** use form requests ([3cd265a](https://github.com/pondi/paperpulse/commit/3cd265a097ff41da6e2d3b3250c7a96e7fe7d801))
* **receipts:** use Inertia resource ([616cad1](https://github.com/pondi/paperpulse/commit/616cad1c4a003f1c14c4baff65a39bb8f4efedb7))
* remove legacy file relationships, use polymorphic extractableEntities ([0954eb0](https://github.com/pondi/paperpulse/commit/0954eb0c9254a24e7fa41bdec12d24495b02b7bd))
* remove legacy file relationships, use polymorphic extractableEntities ([bb0f37a](https://github.com/pondi/paperpulse/commit/bb0f37a2af3d38a47f68e6319fbf0b529214546e))
* remove legacy file relationships, use polymorphic extractableEntities ([97e9799](https://github.com/pondi/paperpulse/commit/97e9799250e15aecd991497ff84304915bb29711))
* remove stale API code and fix empty pages bug ([b8c827b](https://github.com/pondi/paperpulse/commit/b8c827b6ba122ee202f55af7ca4c8b8aca2cfb0e))
* replace cropperjs with custom perspective cropper ([4ccd385](https://github.com/pondi/paperpulse/commit/4ccd38527ac498b1e1a53485283cf138e37cf93a))
* split GeminiProvider, EntityFactory, and SearchService into focused classes ([a70d6bb](https://github.com/pondi/paperpulse/commit/a70d6bb0eb79f81c1acf36fe31bb836942078a8b))
* **tags:** use form requests ([891da47](https://github.com/pondi/paperpulse/commit/891da47827a99b0d4ec0e8a46ec3b813b5b03f9a))
* **textract:** optimize API usage for receipts and documents ([704b104](https://github.com/pondi/paperpulse/commit/704b104215b4ef33fe0661e15ca5e203c120184b))
* **ui:** redesign theme toggle as icon button ([7cf5db7](https://github.com/pondi/paperpulse/commit/7cf5db7be49903d9e279cc655722578c8049c88d))
* use Carbon for all date operations ([7221e98](https://github.com/pondi/paperpulse/commit/7221e98b9b1d2cfc3611cb1f2c81a1c06088960c))
* use Eloquent relationships instead of raw queries in DuplicateController ([c437df3](https://github.com/pondi/paperpulse/commit/c437df37f2c3c6badf6236e2344e2d96e9ed9a23))
* **vouchers:** use Inertia resource ([316770f](https://github.com/pondi/paperpulse/commit/316770f8270746a92b9a793eb3671dfc71aae8c4))


### Documentation

* add PHPDoc warning about BelongsToUser queue/console behavior ([70d08d2](https://github.com/pondi/paperpulse/commit/70d08d2d3c00bb20573010d10dab4361757634b1))
* **api:** document file list/detail responses ([7bcfa75](https://github.com/pondi/paperpulse/commit/7bcfa75c1024f8bd66cdfce32ce28a0cbce005af))
* **changelog:** update for v1.1.0 release ([d4539a1](https://github.com/pondi/paperpulse/commit/d4539a168d221325928a86423d6eead3bb55c7e4))


### Security

* remove API registration and secure invitation requests ([de3b21e](https://github.com/pondi/paperpulse/commit/de3b21e1b1b4836b8d2b046f37d705382a3d7709))
* return 404 for jobs with null file_id in API JobController ([b00e727](https://github.com/pondi/paperpulse/commit/b00e727bca04b74565a87af7f6bbf16b1bfb4f42))
* scope all exists: validation rules to authenticated user ([e2155c4](https://github.com/pondi/paperpulse/commit/e2155c41b9f31aa5d7c3403967ce2f33687a71dd)), closes [#001](https://github.com/pondi/paperpulse/issues/001)
* verify file ownership in UpdateFileRequest::authorize() ([b26ea5c](https://github.com/pondi/paperpulse/commit/b26ea5c3d353c4c27de262cdf55ec3baa57cf96b))


### Performance

* cache search facets and dashboard stats with auto-invalidation ([7c1b9ed](https://github.com/pondi/paperpulse/commit/7c1b9edbb85c90238e84c3cf0cb261e928b2d269))


### Tests

* admin authorization checks ([88663ee](https://github.com/pondi/paperpulse/commit/88663eebf0c5586c698697c10c627a4aef84dc94))
* **api:** cover entity endpoints ([e1ba75f](https://github.com/pondi/paperpulse/commit/e1ba75f6361add45a9c72000dffe85476bf390ce))
* **api:** cover file list/detail responses ([7b2f860](https://github.com/pondi/paperpulse/commit/7b2f860f0b536b755ba04596dcbe04b31bd49a03))
* core service unit tests ([44d1baf](https://github.com/pondi/paperpulse/commit/44d1baf1e4e5f6ed882ad5f2aa19b4e42bc62b08))
* critical controller tests ([be3e4cd](https://github.com/pondi/paperpulse/commit/be3e4cd0a2ef3c3e6bd6e3267149d591e796508a))
* job test coverage for BaseJob, RestartJobChain, ApplyTags, DeleteWorkingFiles, FileJobChainDispatcher ([368fd7d](https://github.com/pondi/paperpulse/commit/368fd7d04bf890ccfedb8564b42ceec53b9b3745))
* migrate DocumentTransformerTest to DocumentInertiaResourceTest ([a9b5a44](https://github.com/pondi/paperpulse/commit/a9b5a44586ba00c455c79b6825f3fdb226738a53))
* multi-tenant data isolation ([6eb11d5](https://github.com/pondi/paperpulse/commit/6eb11d59a03f0a1343d45d11c8dbb3b05008eb4f))
* **suite:** stabilize workflow tests and add upload dedupe coverage ([63f1f57](https://github.com/pondi/paperpulse/commit/63f1f575df94c33427022871428f354ee11cb896))
* **system:** add advanced + service tests + harness ([b1caa06](https://github.com/pondi/paperpulse/commit/b1caa06d75cdfaebded94591380c17d60e77cdc6))
* update feature and unit coverage ([6d3ead5](https://github.com/pondi/paperpulse/commit/6d3ead5e9668b3e0729bb3f6a9c3fa309e8b8f6d))

## [1.1.0] - 2025-12-17

### Added
- File management enhancements
  - SHA-256 deduplication to prevent duplicate uploads
  - Failed file reprocessing with management UI
  - Expanded file statuses and URL management
  - Orphan file record cleanup
  - Configurable pagination for file processing views
  - File streaming via API v1

- Search improvements
  - Natural language OR search with multi-word ranking
  - Unified search endpoint for receipts and documents
  - API v1 search endpoint

- PulseDav enhancements
  - Select all files in folder functionality
  - Document ID support for better tracking

- Job system improvements
  - Enhanced job history with pagination and clickable statistics
  - Hardened monitoring and restart capabilities
  - Improved failure persistence with sanitization

- UI/UX updates
  - Complete Inertia/Vue styling redesign
  - Modernized layouts and navigation
  - Theme toggle redesigned as icon button
  - Refreshed email templates
  - Modernized invitation intake flow

- AI and language features
  - AI outputs now match document language automatically

### Changed
- AI provider migration to gpt-5.2
- Textract processing optimized for better API usage and artifact handling
- Receipt processing now allows operations without merchant information
- Console commands consolidated and cleaned up
- Converted PDFs renamed to archive variant for clarity
- Original file dates now preserved from scanner imports
- Kubernetes deployment support with Horizon fast termination

### Fixed
- Security improvements with authorization checks and enhanced API error responses
- PulseDav race condition in UpdatePulseDavFileStatus job
- GPT-5.2 integration and token limit issues
- Watch functionality prevented from firing on initial mount
- Logging configuration handles empty env vars correctly

## [1.0.0] - 2025-01-07

### Added
- Receipt management
  - Upload via web and PulseDav; view images/PDFs
  - OCR with AWS Textract and AI extraction (OpenAI) to structured data and line items
  - Merchant detection with logo generation, tagging, categories, and sharing (view/edit permissions)
  - Bulk actions: export (CSV/PDF), categorize, delete; analytics dashboard
  - Full‑text search (Meilisearch) with filters and facets

- Document management (beta, feature‑flagged)
  - Upload and process general documents; OCR + AI analysis to title/summary/metadata
  - Suggested categories and tags; search, filters, and categories view
  - Tagging and secure sharing; bulk delete/download; original file download
  - REST API (Sanctum): CRUD, share/unshare, download

- PulseDav WebDAV integration
  - S3‑backed ingestion with scheduled and near‑real‑time sync
  - Folder hierarchy browsing, selection import with tags, temporary download URLs
  - Cleanup and notification jobs for imported/archived files

- Search and discovery
  - Global search endpoint with result facets; Meilisearch indexing for receipts/documents

- Operations and reliability
  - Horizon monitoring; queue health check CLI; retry failed receipt jobs; reliable job restarts

- Collaboration and onboarding
  - Share notifications and invitation flow for new users

- Internationalization and tenancy
  - English and Norwegian UI; strict per‑user data isolation
