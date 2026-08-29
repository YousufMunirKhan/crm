<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Models\MessageTemplate;
use Illuminate\Console\Command;

/**
 * Installs the marketing agent's own template set.
 *
 * The twenty templates already in the system are for sending by hand and are
 * left completely alone - they have a NULL purpose, so the planner never sees
 * them. These are matched and updated by `purpose`, never by name, so renaming
 * one in the UI cannot unhook it.
 *
 * Existing text is not overwritten on a re-run unless --force is passed. Once
 * someone has edited the copy, that is the copy.
 */
class InstallMarketingAgentTemplates extends Command
{
    protected $signature = 'marketing:install-templates {--force : Overwrite copy that has been edited}';

    protected $description = 'Create or refresh the marketing agent email and SMS templates';

    public function handle(): int
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->templates() as $t) {
            $email = EmailTemplate::withTrashed()->where('purpose', $t['purpose'])->first();

            if ($email === null) {
                EmailTemplate::create([
                    'name' => $t['name'],
                    'purpose' => $t['purpose'],
                    'category' => 'marketing-agent',
                    'subject' => $t['subject'],
                    'description' => $t['when'],
                    'content' => $this->wrap($t['body'], $t['preheader'] ?? ''),
                    'is_active' => true,
                ]);
                $created++;
            } elseif ($this->option('force')) {
                $email->update([
                    'subject' => $t['subject'],
                    'description' => $t['when'],
                    'content' => $this->wrap($t['body'], $t['preheader'] ?? ''),
                ]);
                $updated++;
            } else {
                $skipped++;
            }

            if ($t['sms'] === null) {
                continue;
            }

            $sms = MessageTemplate::withTrashed()->where('purpose', $t['purpose'])->first();

            if ($sms === null) {
                MessageTemplate::create([
                    'name' => $t['name'],
                    'purpose' => $t['purpose'],
                    'category' => 'marketing-agent',
                    'message' => $t['sms'],
                    'is_active' => true,
                ]);
                $created++;
            } elseif ($this->option('force')) {
                $sms->update(['message' => $t['sms']]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Created {$created}, updated {$updated}, left alone {$skipped}.");

        if ($skipped > 0 && ! $this->option('force')) {
            $this->line('Existing copy was kept. Pass --force to replace it with the defaults.');
        }

        return self::SUCCESS;
    }

    /**
     * One column, inline styles, no floats - this has to survive Outlook and a
     * phone screen, which is where the old templates fell apart.
     */
    private function wrap(string $inner, string $preheader = ''): string
    {
        // Hidden preheader. Without it the inbox preview scrapes the <h1>, so
        // every email previews as its own subject line said twice.
        $pre = $preheader === '' ? '' :
            '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">'.$preheader
            .str_repeat('&#847;&zwnj;&nbsp;', 40).'</div>';

        return <<<HTML
        {$pre}
        <div style="margin:0;padding:24px 12px;background:#f1f5f9;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;">
            <tr><td style="padding:32px 28px;">
              <img src="{{header_logo_url}}" alt="{{company_name}}" width="150" style="display:block;max-width:150px;height:auto;margin-bottom:28px;border:0;">
              {$inner}
              <p style="margin:28px 0 0;padding-top:20px;border-top:1px solid #e2e8f0;font-size:13px;line-height:20px;color:#64748b;">
                {{company_name}} &middot; <a href="tel:{{company_phone}}" style="color:#2563eb;text-decoration:none;">{{company_phone}}</a><br>
                <a href="{{company_website}}" style="color:#2563eb;text-decoration:none;">{{company_website}}</a>
              </p>
            </td></tr>
          </table>
        </div>
        HTML;
    }

    private function p(string $text): string
    {
        return '<p style="margin:0 0 16px;font-size:16px;line-height:26px;color:#0f172a;">'.$text.'</p>';
    }

    private function h(string $text): string
    {
        return '<h1 style="margin:0 0 16px;font-size:24px;line-height:32px;color:#0f172a;font-weight:700;">'.$text.'</h1>';
    }

    private function cta(string $label): string
    {
        // The number goes in the label. A bare tel: link with a text label is a
        // dead button on a desktop mail client, and a merchant reading in the
        // back office cannot act on it at all.
        return '<p style="margin:24px 0 8px;"><a href="tel:{{company_phone}}" style="display:inline-block;background:#2563eb;color:#ffffff;'
            .'font-size:16px;font-weight:600;text-decoration:none;padding:14px 28px;border-radius:8px;">'.$label.' &mdash; {{company_phone}}</a></p>'
            .'<p style="margin:0 0 4px;font-size:14px;line-height:22px;color:#64748b;">Or just reply to this email and we will call you back.</p>';
    }

    /**
     * @return array<int, array{purpose:string,name:string,when:string,subject:string,body:string,sms:?string}>
     */
    private function templates(): array
    {
        return [
            // ---------------------------------------------------- customers
            [
                'purpose' => 'welcome-onboarding',
                'name' => 'Agent · Welcome (new customer)',
                'when' => 'Sent once, shortly after a customer\'s first order is marked won.',
                'subject' => 'Save this number before you need it',
                'body' => $this->h('Welcome to {{company_name}}')
                    .$this->p('Hi {{first_name}}, thanks for choosing us. Your order is with our setup team. Someone will call you to agree an install date that does not land in your busy hours.')
                    .$this->p('One thing worth saving now: <strong>{{company_phone}}</strong>. That is our support line, answered by people who know your setup — not a call centre. If a terminal stops taking payments on a Saturday night, that is the number.')
                    .$this->p('Nothing to do right now. We will call you.')
                    .$this->cta('Save our number'),
                'sms' => '{{company_name}}: Welcome {{first_name}}! Your setup is booked in and we will call to confirm. Support: {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'licence-renewal',
                'name' => 'Agent · Licence renewal reminder',
                'when' => 'Sent about 30 days before a software licence expires.',
                'subject' => '{{first_name}}, your licence expires soon',
                'body' => $this->h('Your licence is coming up for renewal')
                    .$this->p('Hi {{first_name}}, your {{company_name}} licence is due to expire in the next few weeks.')
                    .$this->p('If it lapses, the till keeps ringing but you lose updates and priority support — and the first you usually notice is when something breaks and the fix takes longer.')
                    .$this->p('A two-minute call sorts it. We can also check whether you are on the right plan, since a few customers are paying for seats they stopped using.')
                    .$this->cta('Renew in two minutes'),
                'sms' => '{{company_name}}: Hi {{first_name}}, your licence expires soon. Call {{company_phone}} to renew and keep support + updates. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'birthday',
                'name' => 'Agent · Birthday',
                'when' => 'Sent on the contact\'s birthday, if one is recorded.',
                'subject' => 'Happy birthday, {{first_name}}',
                'body' => $this->h('Happy birthday, {{first_name}}')
                    .$this->p('From everyone at {{company_name}} — have a good one.')
                    .$this->p('No pitch in this email. Just wanted to say it.'),
                'sms' => '{{company_name}}: Happy birthday {{first_name}}! Have a great day. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'check-in',
                'name' => 'Agent · Quiet customer check-in',
                'when' => 'Sent to a customer with no contact for roughly six months.',
                'subject' => 'All still running OK?',
                'body' => $this->h('Quick check-in')
                    .$this->p('Hi {{first_name}}, it has been a while since we spoke, which usually means everything is working. Worth checking though.')
                    .$this->p('Two things customers often do not realise they can ask us for:')
                    .$this->p('&bull; A free review of your card processing rates — they drift upward and most people never re-check<br>&bull; Staff retraining on the till, at no cost, if you have had turnover')
                    .$this->p('If something has been mildly annoying you for months, tell us. That is usually a ten-minute fix.')
                    .$this->cta('Book a free review'),
                'sms' => '{{company_name}}: Hi {{first_name}}, all running OK? Free card rate review + staff retraining available. Call {{company_phone}}. Reply STOP to opt out.',
            ],

            // ------------------------------------------------- product gaps
            [
                'purpose' => 'epos-upsell',
                'name' => 'Agent · ePOS upsell (has card machine, no ePOS)',
                'when' => 'Customer takes card payments through us but has no ePOS system.',
                'subject' => 'You know the total. Not the items.',
                'body' => $this->h('Your card machine knows how much. It does not know what.')
                    .$this->p('Hi {{first_name}}, you are already taking card payments with us. So at the end of a Saturday you know the total — but not which items shifted, which staff member sold them, or what you nearly ran out of.')
                    .$this->p('That is the gap an ePOS closes. Customers who add one typically stop guessing on two things: what to reorder, and which items are quietly losing money.')
                    .$this->p('It plugs into the terminal you already have, so there is no second machine on the counter and no re-training on payments.')
                    .$this->cta('See what it would cost'),
                'sms' => '{{company_name}}: {{first_name}}, add ePOS to your card machine and see what actually sells, not just the total. Call {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'website-upsell',
                'name' => 'Agent · Website / online ordering upsell',
                'when' => 'Customer has an ePOS with us but no online ordering site.',
                'subject' => 'Stop paying commission on your regulars',
                'body' => $this->h('Orders do not stop when the door shuts')
                    .$this->p('Hi {{first_name}}, you have the till sorted. The orders coming in at 11pm, or from people who will never phone you, are the ones you are not seeing.')
                    .$this->p('An online ordering site takes those — and unlike the delivery apps, the commission stays in your pocket and the customer is <em>yours</em>, not theirs.')
                    .$this->p('It feeds straight into the ePOS you already have, so orders print in the kitchen like any other.')
                    .$this->cta('See a demo site'),
                'sms' => '{{company_name}}: {{first_name}}, take online orders without app commission - straight into your till. Call {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'bundle-upsell',
                'name' => 'Agent · Bundle consolidation',
                'when' => 'Customer has pieces bought separately that a bundle would cover.',
                'subject' => 'Your services, on one bill',
                'body' => $this->h('Same kit, one bill')
                    .$this->p('Hi {{first_name}}, you currently have {{customer_products}} with us — bought at different times, billed separately.')
                    .$this->p('Our bundles cover the same things on one agreement: one invoice, one support number, one renewal date instead of several. Often it works out cheaper too - we will tell you straight if it does not.')
                    .$this->p('Worth two minutes to see the numbers side by side. If it is not cheaper for you, we will say so.')
                    .$this->cta('Compare my bill'),
                'sms' => '{{company_name}}: {{first_name}}, your services are billed separately. A bundle is usually cheaper. Call {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'funding-offer',
                'name' => 'Agent · Business funding',
                'when' => 'Sent to established customers who may need working capital.',
                'subject' => 'Funding that repays as you trade',
                'body' => $this->h('Funding that repays itself as you trade')
                    .$this->p('Hi {{first_name}}, if you have been putting off a refit, a second site, or just need to smooth out a quiet month — this is worth knowing about.')
                    .$this->p('Because we can see your card takings, funding is assessed on what your business actually turns over, not on a form and a credit score. Repayments come out as an agreed percentage of your daily card takings, so a slow week costs you less than a busy one.')
                    .$this->p('No obligation, and we will tell you what the total cost would be before you commit to anything.')
                    .$this->cta('Check my eligibility'),
                'sms' => '{{company_name}}: {{first_name}}, business funding based on your card takings. Repay as you trade. Call {{company_phone}}. Reply STOP to opt out.',
            ],

            // ---------------------------------------------------- prospects
            [
                'purpose' => 'quote-followup',
                'name' => 'Agent · Quotation follow-up',
                'when' => 'A quote was sent and there has been no reply for a couple of weeks.',
                'subject' => 'Shall I close this one off?',
                'body' => $this->h('About that quote')
                    .$this->p('Hi {{first_name}}, we sent you a quote a little while back and have not heard anything — which usually means one of three things.')
                    .$this->p('&bull; The price was higher than you expected<br>&bull; You are mid-way through comparing us with someone else<br>&bull; It simply got buried')
                    .$this->p('All three are fine, and all three are easier to sort on a two-minute call than over email. If the answer is no, tell us that too — we will stop chasing and leave you alone.')
                    .$this->cta('Talk it through'),
                'sms' => '{{company_name}}: Hi {{first_name}}, did our quote land OK? Happy to talk it through or revise it. Call {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'winback',
                'name' => 'Agent · Win-back (lost lead)',
                'when' => 'A lead was marked lost some months ago.',
                'subject' => 'Is your contract up yet?',
                'body' => $this->h('It has been a few months')
                    .$this->p('Hi {{first_name}}, we spoke a while ago and it was not the right time. Fair enough.')
                    .$this->p('One thing has definitely changed since: your current contract is a few months closer to its end date. Most agreements roll over automatically unless you give notice, so it is worth knowing your date even if you do nothing with it.')
                    .$this->p('If it is still a no, no hard feelings — reply and we will close the file properly.')
                    .$this->cta('Get an updated price'),
                'sms' => '{{company_name}}: Hi {{first_name}}, we spoke a while back. Contract nearly up? Worth a fresh price. Call {{company_phone}}. Reply STOP to opt out.',
            ],
            [
                'purpose' => 'prospect-nurture',
                'name' => 'Agent · New prospect nurture',
                'when' => 'A prospect is on file but no lead has been created yet.',
                'subject' => 'Worth re-reading your card statement',
                'body' => $this->h('Three things worth checking')
                    .$this->p('Hi {{first_name}}, you are on our list but we have never properly spoken, so here is something useful rather than a sales pitch.')
                    .$this->p('The three places small businesses most often lose money on payments:')
                    .$this->p('<strong>1. The rate crept up.</strong> Most card processing contracts rise quietly after year one. Almost nobody re-reads the statement.<br><br><strong>2. Paying app commission on your own regulars.</strong> If someone orders from you every week, you are still paying the app a cut of every order - on a customer who found you long ago.<br><br><strong>3. Separate systems that do not talk.</strong> Till, card machine and online orders each doing their own thing means someone reconciles it by hand every night.')
                    .$this->p('If any of those sound familiar, we will do a free review of your current setup and tell you honestly whether switching is worth it.')
                    .$this->cta('Get a free review'),
                'sms' => '{{company_name}}: Hi {{first_name}}, free review of your card rates + till setup. No obligation. Call {{company_phone}}. Reply STOP to opt out.',
            ],
        ];
    }
}
