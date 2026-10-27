<?php

namespace Tests\Feature;

use App\Models\HotelSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HotelSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_settings_and_replace_or_remove_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAsAdmin($user);

        $this->get(route('hotel-settings.index'))->assertOk()->assertSee('Invoice Branding');
        $this->post(route('hotel-settings.store'), [
            'invoice_title' => 'Hotel Invoice',
            'show_logo' => '0',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect(route('hotel-settings.index'));

        $settings = HotelSetting::findOrFail(1);
        $oldPath = $settings->logo_path;
        Storage::disk('public')->assertExists($oldPath);
        $this->assertFalse($settings->show_logo);
        $this->assertEquals($user->id, $settings->updated_by);
        $this->get(route('hotel-settings.index'))->assertOk()->assertSee('name="_method" value="PUT"', false);

        $this->put(route('hotel-settings.update'), [
            'invoice_title' => 'Updated Invoice',
            'logo' => UploadedFile::fake()->image('replacement.png'),
        ])->assertRedirect(route('hotel-settings.index'));
        $settings->refresh();
        $this->assertNotEquals($oldPath, $settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);

        $this->put(route('hotel-settings.update'), ['remove_logo' => '1'])->assertRedirect(route('hotel-settings.index'));
        $this->assertNull($settings->refresh()->logo_path);
        $this->assertDatabaseCount('hotel_settings', 1);
        $this->assertSame('Updated Invoice', $settings->invoice_title);
    }

    public function test_settings_reject_invalid_input_and_untrusted_fields(): void
    {
        $this->actingAsAdmin(User::factory()->create());
        $this->postJson(route('hotel-settings.store'), [
            'company_email' => 'invalid',
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            'show_logo' => 'invalid',
            'updated_by' => 123,
            'logo_path' => 'fake.png',
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_email', 'logo', 'show_logo', 'updated_by', 'logo_path']);
        $this->assertDatabaseCount('hotel_settings', 0);
    }

    public function test_guests_and_non_admins_cannot_access_settings(): void
    {
        $this->get(route('hotel-settings.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('hotel-settings.index'))->assertForbidden();
        $this->postJson(route('hotel-settings.store'), [])->assertForbidden();
        $this->putJson(route('hotel-settings.update'), [])->assertForbidden();
    }
}
