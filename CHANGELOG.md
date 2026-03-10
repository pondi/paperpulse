# Changelog

All notable changes to this project will be documented in this file.

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
