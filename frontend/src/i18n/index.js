import { ref, computed } from 'vue';

const currentLang = ref(localStorage.getItem('hotel_lang') || 'en');

const messages = {
  en: {
    // Brand & Header
    brandTitle: 'Luxury Ethiopian Boutique Stays & Resorts',
    brandTagline: 'Boutique Stays & Heritage Resort PMS',
    guestPortal: 'Guest Portal',
    adminPMS: 'Admin / Staff PMS',
    signIn: 'Sign In',
    signOut: 'Sign Out',
    resortOverview: 'Resort Overview',
    roomDiscovery: 'Suites & Room Booking',
    myBookings: 'My Bookings & Receipts',
    luxuryBannerNotice: 'Boutique Ethiopian Suites & Luxury Resort',

    // Landing Page
    heroBadge: 'Authentic Ethiopian Luxury Hospitality',
    heroTitle: 'Where Ancient Royalty Meets Five-Star Indulgence',
    heroSubtitle: 'Nestled among breathtaking Ethiopian scenery, our luxury boutique resort offers curated suites, bespoke spa rituals, cultural culinary excellence, and touchless digital reservations.',
    checkIn: 'Check-in',
    checkOut: 'Check-out',
    suiteTier: 'Suite Tier',
    allSuites: 'All Available Suites',
    checkRooms: 'Check Rooms',
    whyChooseUsTitle: 'An Unrivaled Ethiopian Experience',
    whyChooseUsSub: 'Every touchpoint is designed to deliver comfort, serenity, and memorable cultural authenticity.',
    featuredSuitesTitle: 'Featured Boutique Suites',
    exploreSuite: 'Explore Suite',

    // Catalog & Booking
    allRooms: 'All Rooms',
    dates: 'Dates',
    nights: 'Night',
    nightsPlural: 'Nights',
    guests: 'Guests',
    adult: 'Adult',
    adults: 'Adults',
    child: 'Child',
    children: 'Children',
    searchPlaceholder: 'Search suites, deluxe, room #...',
    availableForDates: 'Available for Dates',
    bookedForDates: 'Booked for Selected Dates',
    selectRoom: 'Select Room',
    selected: 'Selected',
    unavailable: 'Unavailable',
    showcaseDetails: 'Showcase Details',

    // Booking Drawer
    bookingSummary: 'Booking Summary',
    diningMealPlan: 'Dining & Meal Plan',
    roomOnly: 'Room Only',
    bedAndBreakfast: 'Bed & Breakfast',
    halfBoard: 'Half Board',
    fullBoard: 'Full Board',
    conciergeAddons: 'Concierge & Experience Add-ons',
    airportShuttle: 'VIP Airport Chauffeur Shuttle',
    coffeeCeremony: 'Cultural Coffee Ceremony & Ambasha',
    spaMassage: 'Couple Steam & Massage',
    promoCodeLabel: 'Promo / Discount Code',
    apply: 'Apply',
    baseRoomRate: 'Base Room Rate',
    totalPayable: 'Total Payable',
    guestInfo: 'Guest Information',
    fullNamePlaceholder: 'Full Name (e.g. Almaz Kebede)',
    emailPlaceholder: 'Email Address (for confirmation)',
    phonePlaceholder: 'Phone Number (+251 911...)',
    selectPayment: 'Select Payment Method',
    confirmBookingCTA: 'Confirm & Reserve Suite',
    atomicLocking: 'Atomic Lock in Database...'
  },

  am: {
    // Brand & Header
    brandTitle: 'የኢትዮጵያ ቅንጡ ቡቲክ ሪዞርት እና ሆቴል',
    brandTagline: 'ባህላዊ የኢትዮጵያ የቅንጦት መስተንግዶ እና አስተዳደር',
    guestPortal: 'የእንግዶች ክፍል',
    adminPMS: 'የሆቴል አስተዳደር',
    signIn: 'ይግቡ',
    signOut: 'ይውጡ',
    resortOverview: 'ስለ ሪዞርቱ አጠቃላይ እይታ',
    roomDiscovery: 'ክፍሎችና ማስያዣ',
    myBookings: 'የያዝኳቸው ክፍሎችና ደረሰኝ',
    luxuryBannerNotice: 'የኢትዮጵያ ቅንጡ ሪዞርት እና ባህላዊ መስተንግዶ',

    // Landing Page
    heroBadge: 'ትክክለኛ የኢትዮጵያ የቅንጦት መስተንግዶ',
    heroTitle: 'የጥንታዊ ንግስና ክብር ከዘመናዊ አምስት ኮከብ ቅንጦት ጋር የተገናኘበት',
    heroSubtitle: 'በኢትዮጵያ ውብ የተፈጥሮ እይታዎች የተከበበው ሪዞርታችን ምርጥ ክፍሎችን፣ ባህላዊ የስፓ አገልግሎቶችን፣ የኢትዮጵያ ቡና ስነ-ስርዓትን እና ፈጣን የኦንላይን ማስያዣን ያቀርባል።',
    checkIn: 'የመግቢያ ቀን',
    checkOut: 'የመውጫ ቀን',
    suiteTier: 'የክፍል ደረጃ',
    allSuites: 'ሁሉም ክፍት ክፍሎች',
    checkRooms: 'ክፍሎችን ፈልግ',
    whyChooseUsTitle: 'ተወዳዳሪ የሌለው የኢትዮጵያ መስተንግዶ',
    whyChooseUsSub: 'እያንዳንዱ አገልግሎታችን ምቾትን፣ ሰላምን እና እውነተኛ ባህላዊ ውበትን ለማጎናጸፍ ታስቦ የተዘጋጀ ነው።',
    featuredSuitesTitle: 'ተመራጭ የሪዞርቱ ክፍሎች',
    exploreSuite: 'ክፍሉን ይመልከቱ',

    // Catalog & Booking
    allRooms: 'ሁሉም ክፍሎች',
    dates: 'ቀናት',
    nights: 'ሌሊት',
    nightsPlural: 'ሌሊቶች',
    guests: 'እንግዶች',
    adult: 'አዋቂ',
    adults: 'አዋቂዎች',
    child: 'ህጻን',
    children: 'ህጻናት',
    searchPlaceholder: 'ክፍሎችን፣ ስዊት፣ ቁጥር ይፈልጉ...',
    availableForDates: 'በተመረጡት ቀናት ክፍት ነው',
    bookedForDates: 'በተመረጡት ቀናት ተይዟል',
    selectRoom: 'ይህንን ክፍል ምረጥ',
    selected: 'ተመርጧል',
    unavailable: 'አይገኝም',
    showcaseDetails: 'ዝርዝሩን እይ',

    // Booking Drawer
    bookingSummary: 'የማስያዣ ዝርዝር',
    diningMealPlan: 'የምግብ እና የመስተንግዶ አይነት',
    roomOnly: 'ክፍል ብቻ',
    bedAndBreakfast: 'ቁርስ ያካተተ',
    halfBoard: 'ቁርስ እና እራት',
    fullBoard: 'ቁርስ፣ ምሳ እና እራት',
    conciergeAddons: 'ተጨማሪ የሆቴል አገልግሎቶች',
    airportShuttle: 'የአውሮፕላን ማረፊያ ትራንስፖርት',
    coffeeCeremony: 'ባህላዊ የቡና ስነ-ስርዓትና አምባሻ',
    spaMassage: 'የጥንዶች ስቲም እና ማሳጅ',
    promoCodeLabel: 'የቅናሽ ኩፖን ኮድ',
    apply: 'ተግብር',
    baseRoomRate: 'የክፍሉ መደበኛ ዋጋ',
    totalPayable: 'ጠቅላላ ክፍያ',
    guestInfo: 'የእንግዳው መረጃ',
    fullNamePlaceholder: 'ሙሉ ስም (ለምሳሌ፡ አልማዝ ከበደ)',
    emailPlaceholder: 'ኢሜይል አድራሻ',
    phonePlaceholder: 'ስልክ ቁጥር (+251 9...)',
    selectPayment: 'የክፍያ ዘዴ ይምረጡ',
    confirmBookingCTA: 'ክፍሉን አረጋግጠህ ያዝ',
    atomicLocking: 'ዳታቤዝ ውስጥ በመመዝገብ ላይ...'
  }
};

export function useI18n() {
  const lang = computed(() => currentLang.value);

  function setLang(newLang) {
    if (messages[newLang]) {
      currentLang.value = newLang;
      localStorage.setItem('hotel_lang', newLang);
    }
  }

  function toggleLang() {
    setLang(currentLang.value === 'en' ? 'am' : 'en');
  }

  function t(key) {
    const dict = messages[currentLang.value] || messages.en;
    return dict[key] || messages.en[key] || key;
  }

  return {
    lang,
    setLang,
    toggleLang,
    t
  };
}
